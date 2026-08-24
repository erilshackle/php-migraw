# Migraw

SQL-first migrations for PHP.

**Write SQL. Not magic.**

Migraw is a lightweight, framework-agnostic migration tool for PHP.

Write raw SQL directly or use the fluent `SqlStatement` API while keeping SQL semantics visible.

[![Latest Version](https://img.shields.io/packagist/v/eril/migraw.svg)](https://packagist.org/packages/eril/migraw)
[![Tests](https://img.shields.io/github/actions/workflow/status/erilshackle/php-migraw/tests.yml?branch=main&label=tests)](https://github.com/eril/migraw/actions)
[![PHP Version](https://img.shields.io/packagist/php-v/eril/migraw)](https://packagist.org/packages/eril/migraw)
[![License](https://img.shields.io/packagist/l/eril/migraw)](LICENSE)

---

## Features

- SQL-first migrations
- Raw and fluent migration templates
- Smart migration generation
- Configurable default template
- Lightweight `SqlStatement` API
- Idempotent data population
- Migration batches and rollbacks
- Schema squashing and baseline generation
- Population migration preservation during squash
- Dry-run mode
- Migration validation and checksums
- MySQL and MariaDB support
- PostgreSQL support
- SQLite support
- PDO, Closure and callable connections
- Framework agnostic
- Zero runtime dependencies

---

## Installation

```bash
composer require eril/migraw
```

Generate the configuration:

```bash
php vendor/bin/migraw init
```

Or choose the default database driver:

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

Default configuration:

```php
<?php

return [

    'path' => 'database/migrations',

    'template' => 'raw', // raw | fluent

    'connection' => [
        'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',

        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? '',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',

        'sqlite_path' => $_ENV['DB_SQLITE_PATH']
            ?? 'database/database.sqlite',
    ],

];
```

The same connection structure is used for every driver. Fields not required by the selected driver are ignored.

### Migration Template

Migraw supports two generation styles:

```php
'template' => 'raw',
```

or:

```php
'template' => 'fluent',
```

The default is `raw`.

The selected template is used by:

```bash
php vendor/bin/migraw make <name>
```

### Custom Connections

`connection` may also receive an existing `PDO` instance:

```php
'connection' => new PDO(...),
```

a Closure:

```php
'connection' => static fn (): PDO => Database::connection(),
```

or another callable:

```php
'connection' => [Database::class, 'connection'],
```

Callables must return a `PDO` instance.

A project bootstrap may also be loaded before resolving the connection:

```php
'bootstrap' => 'bootstrap.php',
```

---

## Creating Migrations

Create a migration:

```bash
php vendor/bin/migraw make create_users_table
```

Example:

```text
database/migrations/
└── 20260823143000_create_users_table.php
```

Migraw examines the migration name and generates a suitable migration using the configured template.

### Smart Templates

Common patterns are automatically recognized:

| Migration name | Operation |
| --- | --- |
| `create_users_table` | Create `users` |
| `drop_users_table` | Drop `users` |
| `rename_users_to_members` | Rename `users` to `members` |
| `add_email_to_users` | Add `email` to `users` |
| `remove_email_from_users` | Remove `email` from `users` |
| `create_users_email_index` | Create index |
| `drop_users_email_index` | Drop index |
| `create_unique_users_email` | Create unique constraint |
| `drop_unique_users_email` | Drop unique constraint |

Force a blank raw migration with:

```bash
php vendor/bin/migraw make custom_database_change --sql
```

---

## Raw SQL

Raw is the default migration template:

```php
'template' => 'raw',
```

Example:

```php
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return $this->raw(<<<'SQL'
CREATE TABLE users (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(180) NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
);
SQL);
    }

    public function down(): string|array|SqlStatement
    {
        return $this->raw(<<<'SQL'
DROP TABLE IF EXISTS users;
SQL);
    }
};
```

Use raw SQL when you need complete control, database-specific syntax, or an operation not represented by the fluent API.

---

## Fluent SQL

Set the default template to:

```php
'template' => 'fluent',
```

Migraw then generates migrations using `SqlStatement`:

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

The fluent API stays close to SQL. Column definitions remain explicit SQL fragments:

```php
->column('name VARCHAR(255) NOT NULL')
->column('price DECIMAL(10,2) NOT NULL DEFAULT 0.00')
```

It is not intended to reproduce Laravel's Schema Builder.

---

## Multiple Statements

A migration may return multiple statements:

```php
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

public function down(): array
{
    return [
        $this->drop('users')->ifExists(),
        $this->drop('roles')->ifExists(),
    ];
}
```

Statements execute in the order returned.

Rollback operations should normally use reverse dependency order.

Raw and fluent statements may also be mixed in the same migration.

---

## PopulatorMigration

Use `PopulatorMigration` for deterministic application data such as roles, permissions, statuses and lookup values.

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
                ],
                [
                    'slug' => 'user',
                    'name' => 'User',
                ],
            ],
            uniqueBy: 'slug'
        );
    }
};
```

Population is idempotent. The conflict key should correspond to a `PRIMARY KEY` or `UNIQUE` constraint.

### Updating Existing Rows

By default, existing rows are preserved.

Update selected columns with:

```php
return $this->populateRows(
    'roles',
    [
        [
            'slug' => 'admin',
            'name' => 'System Administrator',
        ],
    ],
    uniqueBy: 'slug'
)->update([
    'name',
]);
```

Composite keys are also supported:

```php
return $this->populateRows(
    'permissions',
    $rows,
    uniqueBy: [
        'resource',
        'action',
    ]
);
```

Population migrations are intended for deterministic application data, not large development fixtures.

---

## Schema Squashing

Long migration histories can be consolidated into a new baseline:

```bash
php vendor/bin/migraw squash
```

Provide a custom baseline name:

```bash
php vendor/bin/migraw squash app_schema
```

Run non-interactively:

```bash
php vendor/bin/migraw squash app_schema --force
```

The squash process:

1. Verifies that schema migrations are up to date.
2. Reads the current database schema.
3. Generates a new baseline migration.
4. Archives superseded schema migrations.
5. Preserves `PopulatorMigration` files.
6. Places populators after the new baseline.
7. Updates the migration repository.

Example:

```text
Before:

database/migrations/
├── 20260601_create_users.php
├── 20260605_create_roles.php
├── 20260606_populate_roles.php
└── 20260610_add_status_to_users.php

After:

database/migrations/
├── 20260823_app_schema.php
├── 20260823_populate_roles.php
└── archive/
    └── 20260823_150000/
        ├── 20260601_create_users.php
        ├── 20260605_create_roles.php
        └── 20260610_add_status_to_users.php
```

Population migrations are not merged into the baseline.

Run pending migrations before squashing:

```bash
php vendor/bin/migraw migrate
php vendor/bin/migraw squash
```

Schema squashing currently supports **MySQL and MariaDB**.

---

## Running Migrations

Run pending migrations:

```bash
php vendor/bin/migraw migrate
```

Rollback the last batch:

```bash
php vendor/bin/migraw rollback
```

Rollback all migrations:

```bash
php vendor/bin/migraw reset
```

Rollback and rerun all migrations:

```bash
php vendor/bin/migraw refresh
```

Rebuild from a clean schema:

```bash
php vendor/bin/migraw fresh
```

Show status:

```bash
php vendor/bin/migraw status
```

Validate migrations:

```bash
php vendor/bin/migraw validate
```

Check configuration and environment:

```bash
php vendor/bin/migraw doctor
```

---

## Dry Run

Preview SQL without modifying the database:

```bash
php vendor/bin/migraw migrate --dry-run
```

Alias:

```bash
php vendor/bin/migraw migrate --pretend
```

---

## Migration Status & Repair

Show executed, pending, missing and modified migrations:

```bash
php vendor/bin/migraw status
```

Modified migrations are detected through stored checksums and may appear as:

```text
[ran modified]
```

Remove records for migration files that no longer exist:

```bash
php vendor/bin/migraw repair
```

Accept the current checksum of intentionally modified migrations:

```bash
php vendor/bin/migraw repair --modified
```

`repair --modified` does not rerun the migration.

---

## Migration Philosophy

Treat executed migrations as immutable.

Instead of changing:

```text
20260601_create_users_table.php
```

create another migration:

```text
20260610_add_phone_to_users.php
```

Schema squashing is different. It creates a new baseline representing the current schema and archives the superseded history.

---

## Commands

| Command | Description |
| --- | --- |
| `init` | Generate the default configuration |
| `init:mysql` | Generate MySQL defaults |
| `init:pgsql` | Generate PostgreSQL defaults |
| `init:sqlite` | Generate SQLite defaults |
| `make <name>` | Create a migration |
| `migrate` | Execute pending migrations |
| `up` | Alias for `migrate` |
| `rollback` | Roll back the last batch |
| `down` | Alias for `rollback` |
| `reset` | Roll back all migrations |
| `refresh` | Roll back and rerun all migrations |
| `fresh` | Rebuild from a clean schema |
| `status` | Display migration status |
| `validate` | Validate migration files |
| `doctor` | Check configuration and environment |
| `repair` | Remove missing migration records |
| `repair --modified` | Accept modified migration checksums |
| `squash [name]` | Create a schema baseline |

Show CLI help:

```bash
php vendor/bin/migraw help
```

---

## Requirements

- PHP 8.2 or newer
- PDO extension

Supported databases:

- MySQL
- MariaDB
- PostgreSQL
- SQLite

Install the corresponding PDO driver:

- `pdo_mysql` — MySQL / MariaDB
- `pdo_pgsql` — PostgreSQL
- `pdo_sqlite` — SQLite

> Schema squashing currently supports MySQL and MariaDB.

---

## Development

Install dependencies:

```bash
composer install
```

Run tests:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Validate Composer configuration:

```bash
composer validate --strict
```

---

## Philosophy

> SQL is already a schema language.

Migraw provides the migration lifecycle without hiding the database.

Choose what fits the migration:

- raw SQL;
- fluent `SqlStatement`;
- smart generation;
- idempotent population;
- schema squashing.

**Write SQL. Not magic.**

---

## License

MIT License

Copyright (c) 2026 Eril TS Carvalho