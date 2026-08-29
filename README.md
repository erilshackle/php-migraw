# Migraw

**SQL-first migrations for PHP. Write SQL. Not magic.**

Migraw is a lightweight, framework-agnostic database migration tool for PHP.

Write migrations using raw SQL when you want full control, or use the fluent API for common schema operations.

[![Latest Version](https://img.shields.io/packagist/v/eril/migraw.svg)](https://packagist.org/packages/eril/migraw)
[![Tests](https://img.shields.io/github/actions/workflow/status/erilshackle/php-migraw/tests.yml?branch=main&label=tests)](https://github.com/eril/migraw/actions)
[![PHP Version](https://img.shields.io/packagist/php-v/eril/migraw)](https://packagist.org/packages/eril/migraw)
[![License](https://img.shields.io/packagist/l/eril/migraw)](LICENSE)

---

## Installation

```bash
composer require eril/migraw
````

Initialize Migraw:

```bash
php vendor/bin/migraw init
```

This creates the default `migraw.php` configuration and migrations directory.

## Quick Start

Create a migration:

```bash
php vendor/bin/migraw make create_users_table
```

Run pending migrations:

```bash
php vendor/bin/migraw migrate
```

Check migration status:

```bash
php vendor/bin/migraw status
```

Roll back the latest batch:

```bash
php vendor/bin/migraw rollback
```

## Raw SQL

Raw SQL is the default migration style.

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
            PRIMARY KEY (id)
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

## Fluent Migrations

Migraw also provides fluent helpers for common schema operations.

```php
public function up(): SqlStatement
{
    return $this->create('users')
        ->id()
        ->column('name VARCHAR(255) NOT NULL')
        ->column('email VARCHAR(180) NOT NULL')
        ->unique('uq_users_email', 'email')
        ->timestamps();
}

public function down(): SqlStatement
{
    return $this->drop('users')->ifExists();
}
```

Set the generated template in `migraw.php`:

```php
'template' => 'raw', // raw | fluent
```

## Population Migrations

Create deterministic application data with:

```bash
php vendor/bin/migraw make populate_roles --populate
```

Migraw supports conflict-aware population for MySQL/MariaDB, PostgreSQL and SQLite.

## Schema Squashing

Compact an old migration history into a new schema baseline:

```bash
php vendor/bin/migraw squash
```

Migraw creates a new baseline from the current database schema, archives the superseded schema migrations, preserves population migrations, and writes a recovery manifest.

Existing installations can safely receive a squashed migration history through the normal migration command:

Restore the previous migration history with:

```bash
php vendor/bin/migraw unsquash
```

Squashing changes the managed migration history without rebuilding the existing database.

## Commands

```text
init[:driver]             Initialize Migraw
make <name>               Create a migration
migrate / up              Run pending migrations
rollback / down           Roll back the latest batch
reset                     Roll back all migrations
refresh                   Roll back and migrate again
fresh                     Rebuild from a clean schema
status                    Show migration status
validate                  Validate migration files
doctor                    Check the environment
repair                    Repair migration records
squash [name]             Create a schema baseline
unsquash [archive]        Restore pre-squash history
help                      Show CLI help
```

Use:

```bash
php vendor/bin/migraw help
```

for the current CLI reference.

## Database Support

| Feature               | MySQL / MariaDB | PostgreSQL | SQLite |
| --------------------- | :-------------: | :--------: | :----: |
| Raw migrations        |        ✓        |      ✓     |    ✓   |
| Fluent migrations     |        ✓        |      ✓     |    ✓   |
| Population migrations |        ✓        |      ✓     |    ✓   |
| Checksums / repair    |        ✓        |      ✓     |    ✓   |
| Schema squash         |        ✓        |      ✓     |    ✓   |
| Squash adoption       |        ✓        |      ✓     |    ✓   |


## Documentation

Full documentation:

[https://erilshackle.github.io/php-migraw/](https://erilshackle.github.io/php-migraw/)

Useful references:

* [Getting Started](https://erilshackle.github.io/php-migraw/getting-started/)
* [Configuration](https://erilshackle.github.io/php-migraw/configuration/)
* [Raw Migrations](https://erilshackle.github.io/php-migraw/migrations/raw/)
* [Fluent Migrations](https://erilshackle.github.io/php-migraw/migrations/fluent/)
* [Population Migrations](https://erilshackle.github.io/php-migraw/migrations/population/)
* [Commands](https://erilshackle.github.io/php-migraw/commands/)
* [Squash & Unsquash](https://erilshackle.github.io/php-migraw/commands/squash/)

## AI / LLM Reference

Migraw provides dedicated references for AI coding assistants:

* [`llms.txt`](https://raw.githubusercontent.com/erilshackle/php-migraw/main/llms.txt) — concise guidance and documentation index
* [`llms-full.txt`](https://raw.githubusercontent.com/erilshackle/php-migraw/main/llms-full.txt) — complete self-contained reference

## License

Migraw is open-source software licensed under the [MIT License](LICENSE).