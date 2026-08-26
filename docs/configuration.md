# Configuration

Migraw uses a `migraw.php` configuration file in the project root.

A typical configuration is:

```php
<?php

return [
    // 'bootstrap' => 'bootstrap.php',

    'path' => 'database/migrations',

    'template' => 'raw',

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
```

## Migration path

```php
'path' => 'database/migrations',
```

Defines where Migraw reads and creates migrations.

## Default template

```php
'template' => 'raw',
```

Supported values:

```text
raw
fluent
```

`raw` keeps generated migrations SQL-first.

`fluent` generates migrations using Migraw's fluent SQL helpers.

## Bootstrap

If your application must be initialized before resolving the database connection:

```php
'bootstrap' => 'bootstrap.php',
```

This is useful for loading environment variables, application containers, helpers, or an existing database layer.

## Connection array

MySQL/MariaDB:

```php
'connection' => [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'app',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
],
```

PostgreSQL:

```php
'connection' => [
    'driver' => 'pgsql',
    'host' => '127.0.0.1',
    'port' => '5432',
    'database' => 'app',
    'username' => 'postgres',
    'password' => '',
    'charset' => 'utf8',
],
```

SQLite:

```php
'connection' => [
    'driver' => 'sqlite',
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => '',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'sqlite_path' => 'database/database.sqlite',
],
```

Only fields required by the selected driver are used.

## Existing PDO connection

Migraw can receive a PDO instance directly:

```php
'connection' => $pdo,
```

## Closure or callable

A closure returning PDO is also supported:

```php
'connection' => static fn (): PDO => DB::connection(),
```

As is any callable returning PDO:

```php
'connection' => [DB::class, 'connection'],
```

This makes it possible to reuse the database connection already managed by your application or framework.
