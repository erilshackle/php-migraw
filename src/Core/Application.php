<?php

namespace Eril\Migraw\Core;

use Eril\Migraw\Migration;
use Eril\Migraw\MigrationCreator;
use Eril\Migraw\MigrationRepository;
use Eril\Migraw\Migrator;
use Eril\Migraw\Sql\SqlStatement;
use PDO;
use RuntimeException;
use Throwable;

final class Application
{
    protected CliOptions $options;

    protected PathResolver $paths;

    protected Config $configManager;

    protected ConnectionResolver $connections;

    protected ?string $command = null;

    protected bool $dryRun = false;

    protected bool $force = false;

    protected bool $blank = false;
    protected bool $populate = false;
    protected bool $repairModified = false;

    public function run(array $argv): int
    {
        $this->options = CliOptions::fromArgv($argv);
        $this->paths = new PathResolver();
        $this->configManager = new Config($this->paths);
        $this->connections = new ConnectionResolver($this->paths);

        $this->command = $this->options->command();
        $this->dryRun = $this->options->hasAny(['--dry-run', '--pretend']);
        $this->force = $this->options->has('--force');
        $this->populate = $this->options->has('--populate');
        $this->blank = $this->options->hasAny(['--blank']);
        $this->repairModified = $this->options->has('--modified');

        try {
            $this->handle();

            return 0;
        } catch (Throwable $e) {
            echo Console::red("Error: {$e->getMessage()}\n");

            return 1;
        }
    }

    protected function handle(): void
    {
        if (in_array($this->command, ['init', '--init'], true)) {
            $this->configManager->init($this->force);
            return;
        }

        if ($this->command !== null && str_starts_with($this->command, 'init:')) {
            $driver = substr($this->command, strlen('init:'));
            $this->configManager->init($this->force, $driver);
            return;
        }

        $this->configManager->loadDefaultBootstrap();

        $config = $this->configManager->load();

        $this->configManager->loadConfiguredBootstrap($config);

        $pdo = $this->connections->resolve($config);

        $path = $this->paths->resolve($config['path'] ?? 'database/migrations');
        $table = $config['table'] ?? 'migrations';

        $repository = new MigrationRepository($pdo, $table);
        $migrator = new Migrator($pdo, $path, $repository);

        if ($this->force) {
            $migrator->force();
        }

        if ($this->dryRun) {
            $migrator->pretend();
        }

        if ($this->command === null) {
            $this->confirmAndMigrate($migrator);
            return;
        }

        match ($this->command) {
            'migrate', 'up' => $this->migrate($migrator),
            'rollback', 'down' => $this->rollback($migrator),
            'status' => $this->status($migrator),
            'validate' => $this->validate($path),
            'doctor' => $this->doctor($config, $pdo, $path, $repository),
            'reset' => $this->resetMigrations($migrator),
            'fresh' => $this->fresh($migrator),
            'make'  => $this->make($path, $this->options->migrationName(), $pdo),
            'repair' => $this->repair($migrator),
            'help', '--help', '-h' => $this->help(),
            default => $this->unknownCommand((string) $this->command),
        };
    }

    protected function migrate(Migrator $migrator): void
    {
        $executed = $migrator->migrate();

        if ($executed === []) {
            echo "Nothing to migrate.\n";
            return;
        }

        if ($this->dryRun) {
            $this->printPretendedSql($migrator);
            return;
        }

        foreach ($executed as $migration) {
            echo Console::green("Migrated: {$migration}\n");
        }
    }

    protected function rollback(Migrator $migrator): void
    {
        $rolledBack = $migrator->rollback();

        if ($rolledBack === []) {
            echo "Nothing to rollback.\n";
            return;
        }

        if ($this->dryRun) {
            $this->printPretendedSql($migrator);
            return;
        }

        foreach ($rolledBack as $migration) {
            echo Console::yellow("Rolled back: {$migration}\n");
        }
    }

    protected function confirmAndMigrate(Migrator $migrator): void
    {
        $pending = $migrator->pending();

        echo Console::bold("Migraw - SQL-first migrations for PHP\n\n");

        if ($pending === []) {
            echo "Nothing to migrate.\n";
            return;
        }

        echo "Pending migrations:\n\n";

        foreach ($pending as $migration) {
            echo Console::gray("  - {$migration}\n");
        }

        echo Console::bold("\nRun these migrations? [y/N]: ");

        $answer = trim((string) fgets(STDIN));

        if (! in_array(strtolower($answer), ['y', 'yes'], true)) {
            echo "Cancelled.\n";
            return;
        }

        echo "\n";

        $executed = $migrator->migrate();

        foreach ($executed as $migration) {
            echo Console::green("Migrated: {$migration}\n");
        }
    }

    protected function resetMigrations(Migrator $migrator): void
    {
        $rolledBack = $migrator->reset();

        if ($rolledBack === []) {
            echo "Nothing to reset.\n";
            return;
        }

        if ($this->dryRun) {
            $this->printPretendedSql($migrator);
            return;
        }

        foreach ($rolledBack as $migration) {
            echo Console::yellow("Rolled back: {$migration}\n");
        }
    }

    protected function fresh(Migrator $migrator): void
    {
        $result = $migrator->fresh();

        if ($result['rolled_back'] === [] && $result['migrated'] === []) {
            echo "Nothing to fresh.\n";
            return;
        }

        if ($this->dryRun) {
            $this->printPretendedSql($migrator);
            return;
        }

        foreach ($result['rolled_back'] as $migration) {
            echo Console::yellow("Rolled back: {$migration}\n");
        }

        foreach ($result['migrated'] as $migration) {
            echo Console::green("Migrated: {$migration}\n");
        }
    }

    protected function status(Migrator $migrator): void
    {
        $rows = $migrator->status();

        if ($rows === []) {
            echo "No migrations found.\n";
            return;
        }

        foreach ($rows as $row) {
            $status = match ($row['status']) {
                'ran'      => Console::green('[ran]'),
                'pending'  => Console::yellow('[pending]'),
                'modified' => Console::yellow('[ran modified]'),
                'missing'  => Console::red('[missing]'),
                default    => "[{$row['status']}]",
            };

            printf("%-24s %s\n", $status, $row['migration']);
        }
    }

    protected function validate(string $path): void
    {
        if (! is_dir($path)) {
            echo Console::red("Migrations directory not found: {$path}\n");
            return;
        }

        $files = glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];

        sort($files);

        if ($files === []) {
            echo "No migration files found.\n";
            return;
        }

        $valid = true;
        $seen = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');

            if (isset($seen[$name])) {
                $valid = false;
                echo Console::red("Duplicate migration name: {$name}\n");
                continue;
            }

            $seen[$name] = true;

            try {
                $migration = require $file;

                if (! $migration instanceof Migration) {
                    $valid = false;
                    echo Console::red("Invalid migration: {$name} must return an instance of Migration.\n");
                    continue;
                }

                $this->validateMigrationReturn($migration->up(), "{$name}::up()");
                $this->validateMigrationReturn($migration->down(), "{$name}::down()");

                echo Console::green("Valid: {$name}\n");
            } catch (Throwable $e) {
                $valid = false;
                echo Console::red("Invalid: {$name} - {$e->getMessage()}\n");
            }
        }

        echo "\n";

        echo $valid
            ? Console::green("All migrations are valid.\n")
            : Console::red("Some migrations are invalid.\n");
    }

    protected function doctor(
        array $config,
        PDO $pdo,
        string $path,
        MigrationRepository $repository
    ): void {
        $issues = 0;

        echo Console::bold("Migraw Doctor\n\n");

        $this->doctorOk('Configuration file', 'migraw.php');

        if (isset($config['bootstrap'])) {
            $bootstrap = $this->paths->resolve((string) $config['bootstrap']);

            if (file_exists($bootstrap)) {
                $this->doctorOk('Bootstrap file', $this->paths->relative($bootstrap));
            } else {
                $issues++;
                $this->doctorFail('Bootstrap file', $this->paths->relative($bootstrap) . ' not found');
            }
        } else {
            $this->doctorOk('Bootstrap file', 'not configured');
        }

        try {
            $pdo->query('SELECT 1');
            $this->doctorOk('Database connection');
        } catch (Throwable $e) {
            $issues++;
            $this->doctorFail('Database connection', $e->getMessage());
        }

        try {
            $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $this->doctorOk('Driver', $driver);
        } catch (Throwable $e) {
            $issues++;
            $this->doctorFail('Driver', $e->getMessage());
        }

        if (is_dir($path)) {
            $this->doctorOk('Migrations path', $this->paths->relative($path));
        } else {
            $issues++;
            $this->doctorFail('Migrations path', $this->paths->relative($path) . ' not found');
        }

        if (is_dir($path) && is_readable($path)) {
            $this->doctorOk('Migrations path readable');
        } else {
            $issues++;
            $this->doctorFail('Migrations path readable');
        }

        if (is_dir($path) && is_writable($path)) {
            $this->doctorOk('Migrations path writable');
        } else {
            $issues++;
            $this->doctorFail('Migrations path writable');
        }

        try {
            $repository->ensureTableExists();
            $this->doctorOk('Migration repository');
        } catch (Throwable $e) {
            $issues++;
            $this->doctorFail('Migration repository', $e->getMessage());
        }

        $files = is_dir($path)
            ? (glob(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [])
            : [];

        $this->doctorOk('Migration files', count($files) . ' found');

        echo "\n";

        echo $issues === 0
            ? Console::green("Ready.\n")
            : Console::red($issues . " issue(s) found.\n");
    }

    protected function doctorOk(string $label, ?string $detail = null): void
    {
        echo Console::green('[ok]') . " {$label}";

        if ($detail !== null && $detail !== '') {
            echo ": {$detail}";
        }

        echo "\n";
    }

    protected function doctorFail(string $label, ?string $detail = null): void
    {
        echo Console::red('[fail]') . " {$label}";

        if ($detail !== null && $detail !== '') {
            echo ": {$detail}";
        }

        echo "\n";
    }

    protected function validateMigrationReturn(mixed $value, string $label): void
    {
        if (
            is_string($value)
            || $value instanceof SqlStatement
        ) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->validateMigrationReturn($item, $label);
            }

            return;
        }

        throw new RuntimeException(
            "{$label} must return string, array, or SqlStatement."
        );
    }

    protected function make(string $path, ?string $name, PDO $pdo): void
    {
        if (! $name) {
            echo "Migration name is required.\n";
            echo "Usage: vendor/bin/migraw make create_users_table\n";
            return;
        }

        if ($this->blank && $this->populate) {
            throw new RuntimeException(
                'The --blank and --populate options cannot be used together.'
            );
        }

        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $creator = new MigrationCreator($path, $driver);

        $file = $creator->create(
            name: $name,
            blank: $this->blank,
            populate: $this->populate
        );

        echo Console::green(
            "Created migration: {$this->paths->relative($file)}\n"
        );
    }

    protected function printPretendedSql(Migrator $migrator): void
    {
        $sql = $migrator->getPretendedSql();

        if ($sql === []) {
            echo "No SQL to display.\n";
            return;
        }

        echo Console::cyan("Dry run:\n\n");

        foreach ($sql as $statement) {
            echo trim($statement) . "\n\n";
        }
    }

    protected function repair(Migrator $migrator): void
    {
        $missing = $migrator->missing();
        $modified = $this->repairModified
            ? $migrator->modified()
            : [];

        echo Console::bold("Migraw Repair\n\n");

        if ($missing === [] && $modified === []) {
            echo $this->repairModified
                ? "No missing or modified migrations found.\n"
                : "No missing migrations found.\n";

            return;
        }

        if ($missing !== []) {
            echo Console::yellow("Missing migrations:\n\n");

            foreach ($missing as $migration) {
                echo "  - {$migration}\n";
            }

            echo "\n";
        }

        if ($modified !== []) {
            echo Console::yellow("Modified migrations:\n\n");

            foreach ($modified as $migration) {
                echo "  - {$migration}\n";
            }

            echo "\n";
        }

        if (! $this->force) {
            $question = $this->repairModified
                ? 'Repair these migration records? [y/N]: '
                : 'Remove missing records from the migration repository? [y/N]: ';

            echo Console::bold($question);

            $answer = trim((string) fgets(STDIN));

            if (! in_array(strtolower($answer), ['y', 'yes'], true)) {
                echo "Cancelled.\n";
                return;
            }
        }

        $removed = [];
        $updated = [];

        if ($missing !== []) {
            $removed = $migrator->repair();
        }

        if ($this->repairModified && $modified !== []) {
            $updated = $migrator->repairModified();
        }

        echo "\n";

        foreach ($removed as $migration) {
            echo Console::green("Removed: {$migration}\n");
        }

        foreach ($updated as $migration) {
            echo Console::green("Updated checksum: {$migration}\n");
        }

        echo "\n";

        if ($removed !== []) {
            echo Console::green(count($removed) . " missing record(s) removed.\n");
        }

        if ($updated !== []) {
            echo Console::green(count($updated) . " checksum(s) updated.\n");
        }
    }

    protected function unknownCommand(string $command): void
    {
        echo "Unknown command: {$command}\n\n";

        $this->help();
    }

    protected function help(): void
    {
        echo <<<TXT
Migraw

Usage:
  migraw init[:mysql|:pgsql|:sqlite] [--force]
  migraw make|new <name> [--populate]
  migraw migrate|up [--dry-run|--pretend]
  migraw rollback|down [--dry-run|--pretend]
  migraw reset [--dry-run|--pretend]
  migraw fresh [--dry-run|--pretend]
  migraw repair [--modified] [--force]
  migraw status
  migraw validate
  migraw doctor
  migraw help

Commands:
  init              Create the default migraw.php config file
  make              Create a new migration file
  migrate, up       Run pending migrations
  rollback, down    Rollback the last migration batch
  reset             Rollback all executed migrations
  fresh             Reset and run all migrations again
  repair            Remove missing migration records
  status            Show migration status
  validate          Validate migration files
  doctor            Check configuration and environment

Options:
  --populate        Generate a PopulatorMigration
  --dry-run         Show SQL without executing it
  --pretend         Alias of --dry-run
  --modified        Also repair modified migration checksums
  --force           Force supported operations

TXT;
    }
}
