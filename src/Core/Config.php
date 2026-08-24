<?php

namespace Eril\Migraw\Core;

use RuntimeException;

final class Config
{
    public function __construct(
        protected PathResolver $paths
    ) {}

    /**
     * Load the conventional project bootstrap file when available.
     */
    public function loadDefaultBootstrap(): void
    {
        $bootstrap = $this->paths->base('bootstrap.php');

        if (file_exists($bootstrap)) {
            require_once $bootstrap;
        }
    }

    /**
     * Load the Migraw configuration file.
     *
     * @return array<string, mixed>
     */
    public function load(): array
    {
        $configPath = $this->paths->base('migraw.php');

        if (! file_exists($configPath)) {
            throw new RuntimeException(
                "Config file not found: migraw.php\n"
                . "Run: php vendor/bin/migraw init"
            );
        }

        $config = require $configPath;

        if (! is_array($config)) {
            throw new RuntimeException(
                'Migraw config file must return an array.'
            );
        }

        return $config;
    }

    /**
     * Load the bootstrap file explicitly configured by the project.
     *
     * @param array<string, mixed> $config
     */
    public function loadConfiguredBootstrap(array $config): void
    {
        if (! isset($config['bootstrap'])) {
            return;
        }

        $bootstrap = $this->paths->resolve(
            (string) $config['bootstrap']
        );

        if (file_exists($bootstrap)) {
            require_once $bootstrap;
        }
    }

    /**
     * Generate the default Migraw configuration.
     */
    public function init(
        bool $force,
        string $driver = 'mysql'
    ): void {
        $configPath = $this->paths->base('migraw.php');

        if (! $force && file_exists($configPath)) {
            echo Console::yellow(
                "Config file already exists: migraw.php\n"
            );

            return;
        }

        file_put_contents(
            $configPath,
            $this->stub($driver)
        );

        $migrationsPath = $this->paths->base(
            'database/migrations'
        );

        if (! is_dir($migrationsPath)) {
            mkdir(
                $migrationsPath,
                0775,
                true
            );

            echo Console::green(
                "Created directory: database/migrations\n"
            );
        }

        echo Console::green(
            "Created config: migraw.php\n"
        );
    }

    /**
     * Build the default configuration stub.
     */
    public function stub(string $driver = 'mysql'): string
    {
        $driver = strtolower($driver);

        $defaults = match ($driver) {
            'sqlite' => [
                'driver' => 'sqlite',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => '',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'sqlite_path' => 'database/database.sqlite',
            ],

            'pgsql' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => '5432',
                'database' => '',
                'username' => 'postgres',
                'password' => '',
                'charset' => 'utf8',
                'sqlite_path' => 'database/database.sqlite',
            ],

            default => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => '',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'sqlite_path' => 'database/database.sqlite',
            ],
        };

        $defaultDriver = var_export(
            $defaults['driver'],
            true
        );

        $defaultHost = var_export(
            $defaults['host'],
            true
        );

        $defaultPort = var_export(
            $defaults['port'],
            true
        );

        $defaultDatabase = var_export(
            $defaults['database'],
            true
        );

        $defaultUsername = var_export(
            $defaults['username'],
            true
        );

        $defaultPassword = var_export(
            $defaults['password'],
            true
        );

        $defaultCharset = var_export(
            $defaults['charset'],
            true
        );

        $defaultSqlitePath = var_export(
            $defaults['sqlite_path'],
            true
        );

        return <<<PHP
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap File
    |--------------------------------------------------------------------------
    |
    | Optional bootstrap file loaded before Migraw resolves the database
    | connection. Useful when the application needs to initialize environment
    | variables, containers, helpers, or its own database layer.
    |
    */

    // 'bootstrap' => 'bootstrap.php',

    /*
    |--------------------------------------------------------------------------
    | Migration Preferences
    |--------------------------------------------------------------------------
    |
    | "path" defines where migration files are stored.
    |
    | "template" defines the default template generated by the make command.
    | Supported values: raw, fluent.
    |
    */

    'path' => 'database/migrations',

    'template' => 'raw',

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The connection may be defined using a configuration array, as shown
    | below.
    |
    | Migraw also accepts:
    |
    | - a PDO instance;
    | - a Closure or callable returning PDO;
    | - a Closure or callable returning a supported connection object;
    | - an object exposing getPdo() or pdo().
    |
    | Examples:
    |
    | 'connection' => new PDO(...),
    | 'connection' => static fn (): PDO => Database::connection(),
    | 'connection' => [Database::class, 'connection'],
    |
    | Only the fields required by the selected driver are used. This allows
    | the same configuration structure to be kept when switching between
    | MySQL, MariaDB, PostgreSQL, and SQLite.
    |
    */

    'connection' => [
        'driver' => \$_ENV['DB_CONNECTION'] ?? {$defaultDriver},

        'host' => \$_ENV['DB_HOST'] ?? {$defaultHost},
        'port' => \$_ENV['DB_PORT'] ?? {$defaultPort},
        'database' => \$_ENV['DB_DATABASE'] ?? {$defaultDatabase},
        'username' => \$_ENV['DB_USERNAME'] ?? {$defaultUsername},
        'password' => \$_ENV['DB_PASSWORD'] ?? {$defaultPassword},
        'charset' => \$_ENV['DB_CHARSET'] ?? {$defaultCharset},

        'sqlite_path' => \$_ENV['DB_SQLITE_PATH'] ?? {$defaultSqlitePath},
    ],

];

PHP;
    }
}