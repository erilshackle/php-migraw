# Migraw

SQL-first migrations for PHP.

**Write SQL. Not magic.**

Migraw is a lightweight migration tool that embraces SQL instead of hiding it.

Write raw SQL when you need complete control, generate smart SQL templates to get started quickly, or use the optional schema builder for common table operations.

---

## Features

* SQL-first migrations
* Raw SQL support
* Lightweight schema builder
* Smart migration templates
* MySQL, MariaDB, PostgreSQL and SQLite support
* Driver-aware schema helpers
* Migration batches
* Rollback support
* Dry-run mode
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

or choose a specific driver:

```bash
php vendor/bin/migraw init:mysql
php vendor/bin/migraw init:pgsql
php vendor/bin/migraw init:sqlsrv
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

Using a connection array:

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

Or provide a PDO instance:

```php
return [

    'path' => 'database/migrations',

    'connection' => new PDO(...),

];
```

Or a callable:

```php
return [
    'bootstrap' => 'bootstrap.php',
    
    'path' => 'database/migrations',

    'connection' => static fn (): PDO => Database::connection(),

];
```

---

## Creating a Migration

Create a migration:

```bash
php vendor/bin/migraw make my_migration_name
```

Example:

```text
database/migrations/
└── 20260627143000_my_migration_name.php
```

---

# Raw SQL

```php
<?php

use Eril\Migraw\Migration;

return new class extends Migration
{
    public function up(): string
    {
        return <<<SQL
        -- Write your UP SQL here
        SQL;
    }

    public function down(): string
    {
        return <<<SQL
        -- Write your DOWN SQL here
        SQL;
    }
};
```
_Migraw will generate a smart SQL template when the migration name matches a known pattern._

---

## Smart Templates

Migraw recognizes common migration names and generates an appropriate SQL template.

| Migration name | Generated SQL |
|----------------|---------------|
| `create_users_table` | `CREATE TABLE users (...)` |
| `create_roles` | `CREATE TABLE roles (...)` |
| `drop_users_table` | `DROP TABLE users` |
| `rename_users_to_members` | `RENAME TABLE users TO members` |
| `add_email_to_users` | `ALTER TABLE users ADD COLUMN email ...` |
| `remove_email_from_users` | `ALTER TABLE users DROP COLUMN email` |
| `create_users_email_index` | `CREATE INDEX idx_users_email ON users(email)` |
| `drop_users_email_index` | `DROP INDEX idx_users_email` |
| `create_unique_users_email` | `ALTER TABLE users ADD CONSTRAINT uq_users_email UNIQUE(email)` |
| `drop_unique_users_email` | `ALTER TABLE users DROP CONSTRAINT uq_users_email` |

_If no known pattern matches, Migraw generates a blank SQL migration instead._

Use `--blank` or `-b` to force an empty raw SQL stub.

```php
return $this->raw(<<<SQL

-- Write here your sql

SQL);
```

---


## SQL Helpers

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

---

## Multiple Statements

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
```

---

## Running Migrations

Run pending migrations:

```bash
php vendor/bin/migraw migrate
php vendor/bin/migraw up
```

Rollback the last batch:

```bash
php vendor/bin/migraw rollback
php vendor/bin/migraw down
```

Rollback every executed migration:

```bash
php vendor/bin/migraw reset
```

Rollback everything and migrate again:

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

Remove missing migration records:

```bash
php vendor/bin/migraw repair
php vendor/bin/migraw repair --modified # 
```

---

## Dry Run

Preview SQL without executing it:

```bash
php vendor/bin/migraw migrate --dry-run
```

or

```bash
php vendor/bin/migraw migrate --pretend
```

---

## Migration Philosophy

Treat migrations as immutable.

Instead of editing an existing migration:

```text
20260601_create_users_table.php
```

create a new one:

```text
20260601_create_users_table.php
20260610_add_phone_to_users.php
20260612_create_roles_table.php
```

This keeps every environment synchronized and preserves migration history.

---

## Philosophy

Migraw is built around a simple idea:

> SQL is already a schema language.

Instead of replacing SQL with a complex abstraction, Migraw keeps SQL visible and explicit.

You choose the level of abstraction that best fits your project:

- Raw SQL
- Smart SQL templates
- Lightweight SQL helpers

Nothing more.

---

## Requirements

* PHP 8.1+
* PDO extension

Supported databases:

* MySQL
* MariaDB
* PostgreSQL
* SQLite

---

## Testing

```bash
composer test
```

---

## License

MIT License

Copyright (c) 2026 Eril TS Carvalho
