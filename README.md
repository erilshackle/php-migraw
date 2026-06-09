# Migraw

SQL-first migrations for PHP. 
Write SQL. Not magic.

Migraw is a lightweight migration tool focused on explicit SQL.

Write raw SQL when you want complete control, or use the optional SQL builder for common operations. No schema diffing, no introspection, no complex DSLs.

## Features

* SQL-first approach
* Raw SQL migrations
* Optional fluent SQL builder
* MySQL, PostgreSQL and SQLite support
* Migration batches
* Rollback support
* Dry-run mode
* Migration integrity checks using checksums
* CLI tooling
* No ORM dependency
* No framework dependency

---

## Installation

```bash
composer require eril/sql-migrator
```

---

## Getting Started

Initialize the configuration file:

```bash
php vendor/bin/migraw init
```

This will create:

```txt
migraw.php
database/
└── migrations/
```

---

## Configuration

```php
<?php

return [

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

You may also provide a PDO instance or callable:

```php
return [

    'path' => 'database/migrations',

    'connection' => function (): PDO {
        return App\Database::connection();
    },

];
```

---

## Creating Migrations

Create a migration:

```bash
vendor/bin/migraw make create_users_table
```

Generated file:

```txt
database/migrations/
└── 2026_06_09_120000_create_users_table.php
```

---

## Raw SQL Migrations

```php
<?php

use Eril\Migraw\Migration;

return new class extends Migration
{
    public function up(): string
    {
        return <<<SQL
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL UNIQUE
        );
        SQL;
    }

    public function down(): string
    {
        return <<<SQL
        DROP TABLE users;
        SQL;
    }
};
```

---

## Fluent SQL Builder

```php
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\Sql;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): SqlStatement
    {
        return Sql::create('users')
            ->field('id INT AUTO_INCREMENT PRIMARY KEY')
            ->field('name VARCHAR(120) NOT NULL')
            ->field('email VARCHAR(160) NOT NULL UNIQUE');
    }

    public function down(): SqlStatement
    {
        return Sql::drop('users');
    }
};
```

---

## Multiple Statements

```php
public function up(): array
{
    return [

        Sql::create('roles')
            ->field('id INT AUTO_INCREMENT PRIMARY KEY')
            ->field('name VARCHAR(80) NOT NULL UNIQUE'),

        Sql::create('users')
            ->field('id INT AUTO_INCREMENT PRIMARY KEY')
            ->field('role_id INT')
            ->constraint(
                'FOREIGN KEY (role_id) REFERENCES roles(id)'
            ),

    ];
}
```

---

## Running Migrations

Run all pending migrations:

```bash
vendor/bin/migraw migrate
```

Rollback the last batch:

```bash
vendor/bin/migraw rollback
```

Rollback all executed migrations:

```bash
vendor/bin/migraw reset
```

Reset and re-run all migrations:

```bash
vendor/bin/migraw refresh
```

Safe refresh alias:

```bash
vendor/bin/migraw fresh
```

Check migration status:

```bash
vendor/bin/migraw status
```

---

## Dry Run

Preview SQL without executing it:

```bash
vendor/bin/migraw migrate --dry-run
```

or

```bash
vendor/bin/migraw migrate --pretend
```

Example:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL
);
```

---

## Migration Integrity

When a migration is executed, Migraw stores a checksum of the migration file.

If the file is modified afterwards, rollback operations are blocked:

```txt
Migration '2026_06_09_120000_create_users_table'
was modified after execution.
```

Status output:

```txt
[ran]       2026_06_09_120000_create_users_table
[modified]  2026_06_09_130000_add_email_to_users
[pending]   2026_06_09_140000_create_posts_table
```

To ignore checksum validation:

```bash
vendor/bin/migraw rollback --force
```

---

## Best Practices

Once a migration has been executed:

**Do not modify it.**

Instead, create a new migration.

Good:

```txt
2026_06_09_create_users_table
2026_06_10_add_phone_to_users
```

Avoid:

```txt
Editing old migration files after deployment
```

---

## Philosophy

Migraw follows a simple principle:

> SQL is already a schema language.

Instead of hiding SQL behind a large abstraction layer, Migraw embraces it.

You can write raw SQL directly or use a lightweight builder when convenient.

No schema diffing.

No database introspection.

No ORM dependency.

No framework dependency.

Just migrations.

---

## Requirements

* PHP 8.1+
* PDO

Supported databases:

* MySQL
* MariaDB
* PostgreSQL
* SQLite

---

## Testing

Run the test suite:

```bash
composer test
```

---

## License

MIT License

Copyright (c) 2026 Eril TS Carvalho
