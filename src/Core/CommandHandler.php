<?php

namespace Eril\Migraw\Core;

use Eril\Migraw\Migration;
use Eril\Migraw\MigrationCreator;
use Eril\Migraw\Squash\MigrationSquasher;
use Eril\Migraw\Squash\MigrationUnsquasher;
use Eril\Migraw\Squash\SchemaDumper;
use Eril\Migraw\Sql\SqlStatement;
use PDO;
use RuntimeException;
use Throwable;

final class CommandHandler
{
    public function __construct(
        protected RuntimeContext $context,
        protected CliOptions $options,
        protected PathResolver $paths
    ) {}

    public function handle(?string $command): void
    {
        $migrator = $this->context->migrator;

        if ($this->force()) {
            $migrator->force();
        }

        if ($this->dryRun()) {
            $migrator->pretend();
        }

        if ($command === null) {
            $this->confirmAndMigrate();
            return;
        }

        match ($command) {
            'migrate', 'up' => $this->migrate(),
            'rollback', 'down' => $this->rollback(),
            'reset' => $this->reset(),
            'refresh' => $this->refresh(),
            'fresh' => $this->fresh(),

            'status' => $this->status(),
            'validate' => $this->validate(),
            'doctor' => $this->doctor(),
            'repair' => $this->repair(),

            'make' => $this->make(),
            'squash' => $this->squash(),
            'unsquash' => $this->unsquash(),

            'help', '--help', '-h' => $this->help(),

            default => $this->unknownCommand($command),
        };
    }

    protected function migrate(): void
    {
        $executed = $this->context->migrator->migrate();

        if ($executed === []) {
            echo "Nothing to migrate.\n";
            return;
        }

        if ($this->dryRun()) {
            $this->printPretendedSql();
            return;
        }

        foreach ($executed as $migration) {
            echo Console::green("Migrated: {$migration}\n");
        }
    }

    protected function rollback(): void
    {
        $rolledBack = $this->context->migrator->rollback();

        if ($rolledBack === []) {
            echo "Nothing to rollback.\n";
            return;
        }

        if ($this->dryRun()) {
            $this->printPretendedSql();
            return;
        }

        foreach ($rolledBack as $migration) {
            echo Console::yellow("Rolled back: {$migration}\n");
        }
    }

    protected function reset(): void
    {
        $rolledBack = $this->context->migrator->reset();

        if ($rolledBack === []) {
            echo "Nothing to reset.\n";
            return;
        }

        if ($this->dryRun()) {
            $this->printPretendedSql();
            return;
        }

        foreach ($rolledBack as $migration) {
            echo Console::yellow("Rolled back: {$migration}\n");
        }
    }

    protected function refresh(): void
    {
        $result = $this->context->migrator->refresh();

        if ($this->dryRun()) {
            $this->printPretendedSql();
            return;
        }

        if ($result['rolled_back'] === [] && $result['migrated'] === []) {
            echo "Nothing to refresh.\n";
            return;
        }

        foreach ($result['rolled_back'] as $migration) {
            echo Console::yellow("Rolled back: {$migration}\n");
        }

        foreach ($result['migrated'] as $migration) {
            echo Console::green("Migrated: {$migration}\n");
        }
    }

    protected function fresh(): void
    {
        $result = $this->context->migrator->fresh();

        if ($result['dropped'] === [] && $result['migrated'] === []) {
            echo "Nothing to fresh.\n";
            return;
        }

        foreach ($result['dropped'] as $table) {
            echo Console::yellow("Dropped table: {$table}\n");
        }

        foreach ($result['migrated'] as $migration) {
            echo Console::green("Migrated: {$migration}\n");
        }
    }

    protected function confirmAndMigrate(): void
    {
        $pending = $this->context->migrator->pending();

        echo Console::bold("Migraw - SQL-first migrations for PHP\n\n");

        if ($pending === []) {
            echo "Nothing to migrate.\n";
            return;
        }

        echo "Pending migrations:\n\n";

        foreach ($pending as $migration) {
            echo Console::gray("  - {$migration}\n");
        }

        if (! $this->confirm('Run these migrations?')) {
            echo "Cancelled.\n";
            return;
        }

        echo "\n";
        $this->migrate();
    }

    protected function status(): void
    {
        $rows = $this->context->migrator->status();

        if ($rows === []) {
            echo "No migrations found.\n";
            return;
        }

        foreach ($rows as $row) {
            $status = match ($row['status']) {
                'ran' => Console::green('[ran]'),
                'pending' => Console::yellow('[pending]'),
                'modified' => Console::yellow('[ran modified]'),
                'missing' => Console::red('[missing]'),
                default => "[{$row['status']}]",
            };

            printf("%-24s %s\n", $status, $row['migration']);
        }
    }

    protected function make(): void
    {
        $name = $this->options->migrationName();

        if (! $name) {
            echo "Migration name is required.\n";
            echo "Usage: vendor/bin/migraw make create_users_table\n";
            return;
        }

        $sql = $this->options->hasAny(['--sql', '--blank']);
        $populate = $this->options->has('--populate');

        if ($sql && $populate) {
            throw new RuntimeException(
                'The --sql and --populate options cannot be used together.'
            );
        }

        $driver = (string) $this->context->pdo->getAttribute(
            PDO::ATTR_DRIVER_NAME
        );

        $creator = new MigrationCreator(
            path: $this->context->path,
            driver: $driver,
            template: $this->context->config['template'] ?? 'raw'
        );

        $file = $creator->create(
            name: $name,
            sql: $sql,
            populate: $populate
        );

        echo Console::green(
            "Created migration: {$this->paths->relative($file)}\n"
        );
    }

    protected function squash(): void
    {
        if ($this->dryRun()) {
            throw new RuntimeException(
                'Squash does not support --dry-run.'
            );
        }

        $name = $this->options->migrationName() ?? 'schema';

        if (! $this->force()) {
            echo Console::bold("Migraw Squash\n\n");
            echo "This will create a schema baseline and archive old schema migrations.\n";

            if (! $this->confirm('Continue?')) {
                echo "Cancelled.\n";
                return;
            }
        }

        $squasher = new MigrationSquasher(
            $this->context->path,
            new SchemaDumper(
                $this->context->pdo,
                $this->context->table
            ),
            $this->context->repository
        );

        $result = $squasher->squash($name);

        echo Console::green(
            "Created baseline: {$this->paths->relative($result['file'])}\n"
        );

        echo Console::yellow(
            'Archived migrations: ' . count($result['archived']) . "\n"
        );

        echo Console::gray(
            "Archive: {$this->paths->relative($result['archive'])}\n"
        );
    }

    protected function unsquash(): void
    {
        if ($this->dryRun()) {
            throw new RuntimeException(
                'Unsquash does not support --dry-run.'
            );
        }

        $archive = $this->options->migrationName();

        if (! $this->force()) {
            echo Console::bold("Migraw Unsquash\n\n");
            echo "This restores the migration history without changing the database schema.\n";

            if (! $this->confirm('Continue?')) {
                echo "Cancelled.\n";
                return;
            }
        }

        $unsquasher = new MigrationUnsquasher(
            $this->context->path,
            $this->context->repository
        );

        $result = $unsquasher->unsquash($archive);

        echo Console::green(
            "Unsquashed: {$result['baseline']}\n"
        );

        echo Console::green(
            "Restored migrations: {$result['restored']}\n"
        );

        echo Console::green(
            "Restored populators: {$result['populators']}\n"
        );
    }

    protected function validate(): void
    {
        $path = $this->context->path;

        if (! is_dir($path)) {
            echo Console::red("Migrations directory not found: {$path}\n");
            return;
        }

        $files = glob(
            rtrim($path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . '*.php'
        ) ?: [];

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
                    throw new RuntimeException(
                        "{$name} must return an instance of Migration."
                    );
                }

                $this->validateMigrationReturn(
                    $migration->up(),
                    "{$name}::up()"
                );

                $this->validateMigrationReturn(
                    $migration->down(),
                    "{$name}::down()"
                );

                echo Console::green("Valid: {$name}\n");
            } catch (Throwable $e) {
                $valid = false;
                echo Console::red(
                    "Invalid: {$name} - {$e->getMessage()}\n"
                );
            }
        }

        echo "\n";

        echo $valid
            ? Console::green("All migrations are valid.\n")
            : Console::red("Some migrations are invalid.\n");
    }

    protected function validateMigrationReturn(
        mixed $value,
        string $label
    ): void {
        if (is_string($value) || $value instanceof SqlStatement) {
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

    protected function doctor(): void
    {
        $issues = 0;
        $config = $this->context->config;
        $pdo = $this->context->pdo;
        $path = $this->context->path;

        echo Console::bold("Migraw Doctor\n\n");

        $this->doctorOk('Configuration file', 'migraw.php');

        if (isset($config['bootstrap'])) {
            $bootstrap = $this->paths->resolve(
                (string) $config['bootstrap']
            );

            if (file_exists($bootstrap)) {
                $this->doctorOk(
                    'Bootstrap file',
                    $this->paths->relative($bootstrap)
                );
            } else {
                $issues++;
                $this->doctorFail(
                    'Bootstrap file',
                    $this->paths->relative($bootstrap) . ' not found'
                );
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
            $this->doctorOk(
                'Migrations path',
                $this->paths->relative($path)
            );
        } else {
            $issues++;
            $this->doctorFail(
                'Migrations path',
                $this->paths->relative($path) . ' not found'
            );
        }

        try {
            $this->context->repository->ensureTableExists();
            $this->doctorOk('Migration repository');
        } catch (Throwable $e) {
            $issues++;
            $this->doctorFail(
                'Migration repository',
                $e->getMessage()
            );
        }

        echo "\n";

        echo $issues === 0
            ? Console::green("Ready.\n")
            : Console::red("{$issues} issue(s) found.\n");
    }

    protected function repair(): void
    {
        $migrator = $this->context->migrator;
        $modifiedEnabled = $this->options->has('--modified');

        $missing = $migrator->missing();
        $modified = $modifiedEnabled
            ? $migrator->modified()
            : [];

        if ($missing === [] && $modified === []) {
            echo "Nothing to repair.\n";
            return;
        }

        if (! $this->force() && ! $this->confirm('Repair migration records?')) {
            echo "Cancelled.\n";
            return;
        }

        foreach ($migrator->repair() as $migration) {
            echo Console::green("Removed: {$migration}\n");
        }

        if ($modifiedEnabled) {
            foreach ($migrator->repairModified() as $migration) {
                echo Console::green(
                    "Updated checksum: {$migration}\n"
                );
            }
        }
    }

    protected function printPretendedSql(): void
    {
        $sql = $this->context->migrator->getPretendedSql();

        if ($sql === []) {
            echo "No SQL to display.\n";
            return;
        }

        echo Console::cyan("Dry run:\n\n");

        foreach ($sql as $statement) {
            echo trim($statement) . "\n\n";
        }
    }

    protected function confirm(string $question): bool
    {
        echo Console::bold("\n{$question} [y/N]: ");

        $answer = strtolower(
            trim((string) fgets(STDIN))
        );

        return in_array($answer, ['y', 'yes'], true);
    }

    protected function force(): bool
    {
        return $this->options->has('--force');
    }

    protected function dryRun(): bool
    {
        return $this->options->hasAny([
            '--dry-run',
            '--pretend',
        ]);
    }

    protected function doctorOk(
        string $label,
        ?string $detail = null
    ): void {
        echo Console::green('[ok]') . " {$label}";

        if ($detail) {
            echo ": {$detail}";
        }

        echo "\n";
    }

    protected function doctorFail(
        string $label,
        ?string $detail = null
    ): void {
        echo Console::red('[fail]') . " {$label}";

        if ($detail) {
            echo ": {$detail}";
        }

        echo "\n";
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
  migraw make <name> [--sql|--populate]
  migraw migrate|up [--dry-run|--pretend]
  migraw rollback|down [--dry-run|--pretend]
  migraw reset [--dry-run|--pretend]
  migraw refresh [--dry-run|--pretend]
  migraw fresh
  migraw repair [--modified] [--force]
  migraw squash [name] [--force]
  migraw unsquash [archive] [--force]
  migraw status
  migraw validate
  migraw doctor
  migraw help

TXT;
    }
}