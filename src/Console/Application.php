<?php

namespace Eril\Migraw\Console;

use Eril\Migraw\MigrationCreator;
use Eril\Migraw\MigrationRepository;
use Eril\Migraw\Migrator;
use PDO;
use RuntimeException;
use Throwable;

final class Application
{
    protected array $args = [];

    protected ?string $command = null;

    protected bool $dryRun = false;

    protected bool $force = false;

    protected bool $blank = false;

    public function run(array $argv): int
    {
        $this->args = $argv;
        $this->command = $argv[1] ?? null;
        $this->dryRun = $this->hasOption('--dry-run') || $this->hasOption('--pretend');
        $this->force = $this->hasOption('--force');
        $this->blank = $this->hasOption('--blank') || $this->hasOption('-b');

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
            $this->initConfig($this->force);
            return;
        }

        if ($this->command !== null && str_starts_with($this->command, 'init:')) {
            $driver = substr($this->command, strlen('init:'));
            $this->initConfig($this->force, $driver);
            return;
        }

        $this->loadDefaultBootstrap();

        $config = $this->loadConfig();

        $this->loadConfiguredBootstrap($config);

        $pdo = $this->resolvePdo($config);

        $path = $this->resolvePath($config['path'] ?? 'database/migrations');
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
            'make', 'new' => $this->make($path, $this->migrationNameArgument(), $pdo),
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
                'ran' => Console::green('[ran]'),
                'pending' => Console::yellow('[pending]'),
                'modified' => Console::red('[modified]'),
                'missing' => Console::red('[missing]'),
                default => "[{$row['status']}]",
            };

            printf("%-14s %s\n", $status, $row['migration']);
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

                if (! $migration instanceof \Eril\Migraw\Migration) {
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

        if ($valid) {
            echo Console::green("All migrations are valid.\n");
            return;
        }

        echo Console::red("Some migrations are invalid.\n");
    }


    /**
     * Check Migraw configuration and environment.
     *
     * @param array<string,mixed> $config Migraw configuration.
     * @param PDO $pdo Database connection.
     * @param string $path Migration directory path.
     * @param MigrationRepository $repository Migration repository.
     *
     * @return void
     */
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
            $bootstrap = $this->resolvePath((string) $config['bootstrap']);

            if (file_exists($bootstrap)) {
                $this->doctorOk('Bootstrap file', $this->relativePath($bootstrap));
            } else {
                $issues++;
                $this->doctorFail('Bootstrap file', $this->relativePath($bootstrap) . ' not found');
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
            $this->doctorOk('Migrations path', $this->relativePath($path));
        } else {
            $issues++;
            $this->doctorFail('Migrations path', $this->relativePath($path) . ' not found');
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

        if ($issues === 0) {
            echo Console::green("Ready.\n");
            return;
        }

        echo Console::red($issues . " issue(s) found.\n");
    }

    /**
     * Print a successful doctor check line.
     *
     * @param string $label Check label.
     * @param string|null $detail Optional detail.
     *
     * @return void
     */
    protected function doctorOk(string $label, ?string $detail = null): void
    {
        echo Console::green('[ok]') . " {$label}";

        if ($detail !== null && $detail !== '') {
            echo ": {$detail}";
        }

        echo "\n";
    }

    /**
     * Print a failed doctor check line.
     *
     * @param string $label Check label.
     * @param string|null $detail Optional detail.
     *
     * @return void
     */
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
            || $value instanceof \Eril\Migraw\Sql\SqlStatement
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

    /**
     * Resolve the migration name argument from the command line.
     *
     * This allows both:
     *
     * - migraw make create_users_table
     * - migraw make -b create_users_table
     *
     * @return string|null
     */
    protected function migrationNameArgument(): ?string
    {
        foreach (array_slice($this->args, 2) as $arg) {
            if (str_starts_with($arg, '-')) {
                continue;
            }

            return $arg;
        }

        return null;
    }

    /**
     * Create a new migration file.
     *
     * @param string $path Migration directory path.
     * @param string|null $name Migration name.
     * @param PDO $pdo Database connection.
     *
     * @return void
     */
    protected function make(string $path, ?string $name, PDO $pdo): void
    {
        if (! $name) {
            echo "Migration name is required.\n";
            echo "Usage: vendor/bin/migraw make create_users_table\n";
            return;
        }

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $creator = new MigrationCreator($path, $driver);
        $file = $creator->create($name, $this->blank);

        echo Console::green("Created migration: {$this->relativePath($file)}\n");
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

    /**
     * Remove missing migration records from the repository.
     *
     * @param Migrator $migrator
     *
     * @return void
     */
    protected function repair(Migrator $migrator): void
    {
        $missing = $migrator->missing();

        echo Console::bold("Migraw Repair\n\n");

        if ($missing === []) {
            echo "No missing migrations found.\n";
            return;
        }

        echo Console::yellow("Missing migrations:\n\n");

        foreach ($missing as $migration) {
            echo "  - {$migration}\n";
        }

        if (! $this->force) {
            echo Console::bold("\nRemove these records from the migration repository? [y/N]: ");

            $answer = trim((string) fgets(STDIN));

            if (! in_array(strtolower($answer), ['y', 'yes'], true)) {
                echo "Cancelled.\n";
                return;
            }
        }

        $removed = $migrator->repair();

        echo "\n";

        foreach ($removed as $migration) {
            echo Console::green("Removed: {$migration}\n");
        }

        echo "\n";
        echo Console::green(count($removed) . " record(s) removed.\n");
    }

    protected function loadDefaultBootstrap(): void
    {
        $bootstrap = $this->basePath('bootstrap.php');

        if (file_exists($bootstrap)) {
            require_once $bootstrap;
        }
    }

    protected function loadConfig(): array
    {
        $configPath = $this->basePath('migraw.php');

        if (! file_exists($configPath)) {
            throw new RuntimeException(
                "Config file not found: migraw.php\nRun: php vendor/bin/migraw init"
            );
        }

        $config = require $configPath;

        if (! is_array($config)) {
            throw new RuntimeException('Migraw config file must return an array.');
        }

        return $config;
    }

    protected function loadConfiguredBootstrap(array $config): void
    {
        if (! isset($config['bootstrap'])) {
            return;
        }

        $bootstrap = $this->resolvePath($config['bootstrap']);

        if (file_exists($bootstrap)) {
            require_once $bootstrap;
        }
    }

    protected function resolvePdo(array $config): PDO
    {
        $pdo = $config['pdo'] ?? $config['connection'] ?? null;

        if (is_callable($pdo)) {
            $pdo = $pdo();
        }

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        if (! is_array($pdo)) {
            throw new RuntimeException('Database connection must be a PDO instance, callable, or connection array.');
        }

        return $this->createPdoFromConnection($pdo, $config['options'] ?? []);
    }

    protected function createPdoFromConnection(array $connection, array $options = []): PDO
    {
        $driver = $connection['driver'] ?? 'mysql';

        $options = $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = match ($driver) {
            'sqlite' => 'sqlite:' . $this->resolvePath(
                $connection['sqlite_path'] ?? 'database/database.sqlite'
            ),

            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $connection['host'] ?? '127.0.0.1',
                $connection['port'] ?? '5432',
                $connection['database'] ?? ''
            ),

            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $connection['host'] ?? '127.0.0.1',
                $connection['port'] ?? '3306',
                $connection['database'] ?? '',
                $connection['charset'] ?? 'utf8mb4'
            ),

            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };

        $username = $driver === 'sqlite' ? null : ($connection['username'] ?? null);
        $password = $driver === 'sqlite' ? null : ($connection['password'] ?? null);

        return new PDO($dsn, $username, $password, $options);
    }



    protected function initConfig(bool $forced, string $driver = 'mysql'): void
    {
        $configPath = $this->basePath('migraw.php');

        if (! $forced && file_exists($configPath)) {
            echo "Config file already exists: migraw.php\n";
            return;
        }

        file_put_contents($configPath, $this->defaultConfigStub($driver));

        $migrationsPath = $this->basePath('database/migrations');

        if (! is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0775, true);
            echo Console::green("Created directory: database/migrations\n");
        }

        echo Console::green("Created config: migraw.php\n");
    }

    protected function defaultConfigStub(string $driver = 'mysql'): string
    {
        $driver = strtolower($driver);

        $connection = match ($driver) {
            'sqlite' => <<<'PHP'
'connection' => [
        'driver' => 'sqlite',
        'sqlite_path' => 'database/database.sqlite',
    ]
PHP,

            'pgsql' => <<<'PHP'
'connection' => [
        'driver' => $_ENV['DB_CONNECTION'] ?? 'pgsql',

        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '5432',
        'database' => $_ENV['DB_DATABASE'] ?? '',
        'username' => $_ENV['DB_USERNAME'] ?? 'postgres',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
    ]
PHP,

            default => <<<'PHP'
'connection' => [
        'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',

        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? '',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',

        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ]
PHP,
        };

        return <<<PHP
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap File
    |--------------------------------------------------------------------------
    |
    | Optional file loaded before the migrator starts.
    | Useful for loading environment variables, constants,
    | framework bootstrap files, or application services.
    |
    */

    // 'bootstrap' => 'bootstrap.php',

    /*
    |--------------------------------------------------------------------------
    | Migrations Path
    |--------------------------------------------------------------------------
    |
    | Directory where your migration files are stored.
    |
    */

    'path' => 'database/migrations',

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | You may configure the connection using an array, a PDO instance,
    | or a callable that returns a PDO instance.
    |
    */

    {$connection},

];

PHP;
    }

    protected function resolvePath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return $this->basePath($path);
    }

    protected function basePath(string $path = ''): string
    {
        $base = getcwd();

        if ($path === '') {
            return $base;
        }

        return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/\\'));
    }

    protected function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }

    protected function relativePath(string $path): string
    {
        return ltrim(str_replace((string) getcwd(), '', $path), DIRECTORY_SEPARATOR);
    }

    protected function hasOption(string $option): bool
    {
        return in_array($option, $this->args, true);
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
  migraw make|new <name> [--blank|-b]
  migraw migrate|up [--dry-run|--pretend]
  migraw rollback|down [--dry-run|--pretend]
  migraw reset [--dry-run|--pretend]
  migraw fresh [--dry-run|--pretend]
  migraw repair [--force]
  migraw status
  migraw validate
  migraw doctor
  migraw help

Commands:
  init              Create the default migraw.php config file
  make, new         Create a new migration file
  migrate, up       Run pending migrations
  rollback, down    Rollback the last migration batch
  reset             Rollback all executed migrations
  fresh             Reset and run all migrations again
  repair            Remove missing migration records
  status            Show migration status
  validate          Validate migration files
  doctor            Check configuration and environment

Options:
  -b, --blank       Generate a blank migration stub
  --dry-run         Show SQL without executing it
  --pretend         Alias of --dry-run
  --force           Force supported operations

TXT;
    }
}
