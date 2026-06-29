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

    'path' => 'database/migrations',

    'connection' => static fn (): PDO => Database::connection(),

];
```

---

## Creating a Migration

Create an empty migration:

```bash
php vendor/bin/migraw make create_users_table
```

Example:

```text
database/migrations/
└── 20260627143000_create_users_table.php
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
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL
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

## Smart Templates

Migraw can generate SQL templates based on the migration name.

Example:

```bash
php vendor/bin/migraw make create_users_table -t
```

generates:
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

# Schema Builder

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
        $this->drop('users')
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
php vendor/bin/migraw
```

or

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
- Lightweight schema builder

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
