# Migraw

SQL-first migrations for PHP.

**Write SQL. Not magic.**

Migraw is a lightweight migration tool that embraces SQL instead of hiding it.

Write raw SQL when you need complete control, generate smart SQL templates from migration names, use lightweight schema helpers for common operations, and populate essential data idempotently.

---

## Features

* SQL-first migrations
* Raw SQL support
* Smart migration templates
* Lightweight schema helpers
* Idempotent data population
* MySQL and MariaDB support
* PostgreSQL support
* SQLite support
* Migration batches
* Rollback support
* Dry-run mode
* Migration validation
* Modified migration detection
* Interactive CLI
* PDO or callable connection
* Framework agnostic
* Zero runtime dependencies

---

## Installation

```bash
composer require eril/migraw
```

---

## Getting Started

Generate the default configuration:

```bash
php vendor/bin/migraw init
```

Or choose a specific database driver:

```bash
php vendor/bin/migraw init:mysql
php vendor/bin/migraw init:pgsql
php vendor/bin/migraw init:sqlite
```

This creates:

```text
migraw.php

database/
└── migrations/
```

---

## Configuration

### Connection array

```php
<?php

return [
    'path' => 'database/migrations',

    'connection' => [
        'driver' => 'mysql',

        'host' => '127.0.0.1',
        'port' => '3306',
        'database' => 'example',

        'username' => 'root',
        'password' => '',

        'charset' => 'utf8mb4',
    ],
];
```

### PDO instance

```php
<?php

return [
    'path' => 'database/migrations',

    'connection' => new PDO(...),
];
```

### Callable connection

```php
<?php

return [
    'bootstrap' => 'bootstrap.php',

    'path' => 'database/migrations',

    'connection' => static fn (): PDO => Database::connection(),
];
```

The callable must return a `PDO` instance.

---

## Creating Migrations

Create a migration:

```bash
php vendor/bin/migraw make my_migration_name
```

Example output:

```text
database/migrations/
└── 20260627143000_my_migration_name.php
```

Migraw examines the migration name and generates a suitable template when it recognizes a supported pattern.

---

## Smart Templates

Migraw recognizes common migration names and generates corresponding SQL templates.

| Migration name              | Generated SQL                                                   |
| --------------------------- | --------------------------------------------------------------- |
| `create_users_table`        | `CREATE TABLE users (...)`                                      |
| `create_roles`              | `CREATE TABLE roles (...)`                                      |
| `drop_users_table`          | `DROP TABLE users`                                              |
| `rename_users_to_members`   | `RENAME TABLE users TO members`                                 |
| `add_email_to_users`        | `ALTER TABLE users ADD COLUMN email ...`                        |
| `remove_email_from_users`   | `ALTER TABLE users DROP COLUMN email`                           |
| `create_users_email_index`  | `CREATE INDEX idx_users_email ON users(email)`                  |
| `drop_users_email_index`    | `DROP INDEX idx_users_email`                                    |
| `create_unique_users_email` | `ALTER TABLE users ADD CONSTRAINT uq_users_email UNIQUE(email)` |
| `drop_unique_users_email`   | `ALTER TABLE users DROP CONSTRAINT uq_users_email`              |

When no supported pattern matches, Migraw generates a blank raw SQL migration.

Force a blank migration using plain SQL strings with `--sql`:

```bash
php vendor/bin/migraw make custom_database_change --sql
```

---

## Raw SQL

A raw SQL migration keeps the SQL completely explicit:

```php
<?php

use Eril\Migraw\Migration;

return new class extends Migration
{
    public function up(): string
    {
        return $this->raw(<<<SQL
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(180) NOT NULL UNIQUE
        )
        SQL);
    }

    public function down(): string
    {
        return $this->raw(<<<SQL
        DROP TABLE users
        SQL);
    }
};
```

Raw SQL is useful when:

* the database provides specialized syntax;
* exact control over the generated statement is required;
* a helper would make the operation less clear;
* the migration cannot be represented by the available helpers.

---

## SQL Helpers

Migraw provides lightweight helpers for common schema operations.

```php
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): SqlStatement
    {
        return $this->create('users')
            ->id()
            ->column('name VARCHAR(255) NOT NULL')
            ->column('email VARCHAR(180) NOT NULL UNIQUE')
            ->column('password_hash VARCHAR(255) NOT NULL')
            ->timestamps();
    }

    public function down(): SqlStatement
    {
        return $this->drop('users')
            ->ifExists();
    }
};
```

The helpers do not attempt to replace SQL. They provide concise builders for frequent operations while preserving the generated SQL model.

---

## Multiple Statements

A migration may return multiple statements:

```php
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    /**
     * @return array<int, SqlStatement>
     */
    public function up(): array
    {
        return [
            $this->create('roles')
                ->id()
                ->column('name VARCHAR(80) NOT NULL UNIQUE'),

            $this->create('users')
                ->id()
                ->column('role_id INT NOT NULL')
                ->foreign('role_id', 'roles'),
        ];
    }

    /**
     * @return array<int, SqlStatement>
     */
    public function down(): array
    {
        return [
            $this->drop('users')->ifExists(),
            $this->drop('roles')->ifExists(),
        ];
    }
};
```

Statements are executed in the order in which they are returned.

Rollback statements should therefore normally appear in reverse dependency order.

---

## Populating Data

Migraw can generate idempotent data-population migrations for essential application data.

Create one with:

```bash
php vendor/bin/migraw make populate_roles --populate
```

Example:

```php
<?php

use Eril\Migraw\PopulatorMigration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends PopulatorMigration
{
    public function populate(): SqlStatement
    {
        return $this->populateRows(
            'roles',
            [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                    'active' => true,
                ],
                [
                    'slug' => 'user',
                    'name' => 'User',
                    'active' => true,
                ],
            ],
            uniqueBy: 'slug'
        );
    }
};
```

The exact helper name should match the `PopulatorMigration` API in the installed version. A population statement can also be created directly through the SQL facade:

```php
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\Sql;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): SqlStatement
    {
        return Sql::populate(
            'roles',
            [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                    'active' => true,
                ],
                [
                    'slug' => 'user',
                    'name' => 'User',
                    'active' => true,
                ],
            ],
            uniqueBy: 'slug'
        );
    }

    public function down(): array
    {
        return [];
    }
};
```

### Updating existing rows

By default, conflicting rows are preserved.

Use `update()` to update selected columns when an existing row matches the unique key:

```php
return Sql::populate(
    'roles',
    [
        [
            'slug' => 'admin',
            'name' => 'System Administrator',
            'active' => true,
        ],
    ],
    uniqueBy: 'slug'
)->update([
    'name',
    'active',
]);
```

Conflict detection requires a corresponding `PRIMARY KEY` or `UNIQUE` constraint in the database.

### Composite conflict keys

Multiple columns may identify a row:

```php
return Sql::populate(
    'permissions',
    $rows,
    uniqueBy: [
        'resource',
        'action',
    ]
);
```

Population migrations should be used for deterministic data required by the application, such as:

* roles;
* permissions;
* default statuses;
* system configuration;
* fixed lookup values.

They are not intended to generate large volumes of development or demonstration data.

---

## Running Migrations

Run all pending migrations:

```bash
php vendor/bin/migraw migrate
```

Alias:

```bash
php vendor/bin/migraw up
```

Rollback the last migration batch:

```bash
php vendor/bin/migraw rollback
```

Alias:

```bash
php vendor/bin/migraw down
```

Rollback every executed migration:

```bash
php vendor/bin/migraw reset
```

Rollback all migrations and execute them again:

```bash
php vendor/bin/migraw refresh
```

Drop the existing schema and run all migrations from a clean database:

```bash
php vendor/bin/migraw fresh
```

Show migration status:

```bash
php vendor/bin/migraw status
```

Validate migration files:

```bash
php vendor/bin/migraw validate
```

Check configuration and environment:

```bash
php vendor/bin/migraw doctor
```

---

## Migration Status

Display executed, pending, missing, or modified migrations:

```bash
php vendor/bin/migraw status
```

A modified executed migration may be reported as:

```text
[ran modified]
```

Migraw detects this by comparing the current migration checksum with the checksum recorded when the migration was executed.

---

## Repairing Migration Records

Remove records whose migration files no longer exist:

```bash
php vendor/bin/migraw repair
```

Accept the current contents of executed migrations that were intentionally modified:

```bash
php vendor/bin/migraw repair --modified
```

The `--modified` option updates the stored checksums. It does not execute the changed migration again.

Editing executed migrations is generally discouraged. Use this option only when the modification is intentional and every affected environment is understood.

---

## Dry Run

Preview migration SQL without executing it:

```bash
php vendor/bin/migraw migrate --dry-run
```

Alias:

```bash
php vendor/bin/migraw migrate --pretend
```

Dry-run mode displays the planned operations without modifying the database or migration repository.

---

## Migration Philosophy

Treat executed migrations as immutable.

Instead of editing an existing migration:

```text
20260601_create_users_table.php
```

create a new migration:

```text
20260601_create_users_table.php
20260610_add_phone_to_users.php
20260612_create_roles_table.php
```

This keeps environments synchronized and preserves migration history.

`repair --modified` exists as a recovery and maintenance mechanism, not as the standard migration workflow.

---

## Commands

| Command             | Description                                      |
| ------------------- | ------------------------------------------------ |
| `init`              | Generate the default configuration               |
| `init:mysql`        | Generate a MySQL configuration                   |
| `init:pgsql`        | Generate a PostgreSQL configuration              |
| `init:sqlite`       | Generate an SQLite configuration                 |
| `make <name>`       | Create a migration                               |
| `migrate`           | Execute pending migrations                       |
| `up`                | Alias for `migrate`                              |
| `rollback`          | Roll back the last batch                         |
| `down`              | Alias for `rollback`                             |
| `reset`             | Roll back all migrations                         |
| `refresh`           | Roll back and rerun all migrations               |
| `fresh`             | Rebuild the database from a clean schema         |
| `status`            | Display migration status                         |
| `validate`          | Validate migration files                         |
| `doctor`            | Check configuration and environment              |
| `repair`            | Remove missing migration records                 |
| `repair --modified` | Accept current checksums for modified migrations |

Available command options may be displayed with:

```bash
php vendor/bin/migraw help
```

---

## Philosophy

Migraw is built around a simple idea:

> SQL is already a schema language.

Instead of replacing SQL with a complex abstraction, Migraw keeps SQL visible and explicit.

Choose the level of abstraction that best fits each migration:

* raw SQL;
* smart migration templates;
* lightweight SQL helpers;
* idempotent data population.

Nothing more.

---

## Requirements

* PHP 8.2 or newer
* PDO extension

Supported databases:

* MySQL
* MariaDB
* PostgreSQL
* SQLite

The appropriate PDO driver must be installed for the selected database.

Examples:

* `pdo_mysql` for MySQL and MariaDB;
* `pdo_pgsql` for PostgreSQL;
* `pdo_sqlite` for SQLite.

---

## Development

Install development dependencies:

```bash
composer install
```

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Run all configured project checks:

```bash
composer test
composer analyse
composer validate --strict
```

---

## License

MIT License

Copyright (c) 2026 Eril TS Carvalho
