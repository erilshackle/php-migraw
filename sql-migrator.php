<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap File
    |--------------------------------------------------------------------------
    |
    | Optional file loaded before the migrator starts.
    | Useful for loading Composer, environment variables, constants,
    | framework bootstrap files, or application services.
    |
    */

    'bootstrap' => 'bootstrap.php',

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

    'connection' => [
        'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',

        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? '',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',

        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',

        'sqlite_path' => $_ENV['DB_SQLITE_PATH'] ?? 'database/database.sqlite',
    ],

];