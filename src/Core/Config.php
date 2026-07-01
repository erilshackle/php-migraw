<?php

namespace Eril\Migraw\Core;

use RuntimeException;

final class Config
{
    public function __construct(
        protected PathResolver $paths
    ) {}

    public function loadDefaultBootstrap(): void
    {
        $bootstrap = $this->paths->base('bootstrap.php');

        if (file_exists($bootstrap)) {
            require_once $bootstrap;
        }
    }

    public function load(): array
    {
        $configPath = $this->paths->base('migraw.php');

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

    public function loadConfiguredBootstrap(array $config): void
    {
        if (! isset($config['bootstrap'])) {
            return;
        }

        $bootstrap = $this->paths->resolve((string) $config['bootstrap']);

        if (file_exists($bootstrap)) {
            require_once $bootstrap;
        }
    }

    public function init(bool $force, string $driver = 'mysql'): void
    {
        $configPath = $this->paths->base('migraw.php');

        if (! $force && file_exists($configPath)) {
            echo Console::yellow("Config file already exists: migraw.php\n");
            return;
        }

        file_put_contents($configPath, $this->stub($driver));

        $migrationsPath = $this->paths->base('database/migrations');

        if (! is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0775, true);
            echo Console::green("Created directory: database/migrations\n");
        }

        echo Console::green("Created config: migraw.php\n");
    }

    public function stub(string $driver = 'mysql'): string
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
    */

    // 'bootstrap' => 'bootstrap.php',

    /*
    |--------------------------------------------------------------------------
    | Migrations Path
    |--------------------------------------------------------------------------
    */

    'path' => 'database/migrations',

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    */

    {$connection},

];

PHP;
    }
}