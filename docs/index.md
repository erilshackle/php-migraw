# Migraw

**SQL-first migrations for PHP. Write SQL. Not magic.**

Migraw is a lightweight, framework-agnostic database migration tool for PHP.

It keeps SQL visible while providing the migration workflow expected from a modern migration system.

## Install

```bash
composer require eril/migraw
```

Initialize Migraw:

```bash
php vendor/bin/migraw init
```

Create a migration:

```bash
php vendor/bin/migraw make create_users_table
```

Run migrations:

```bash
php vendor/bin/migraw migrate
```

## What Migraw provides

- Raw SQL migrations
- Fluent SQL helpers
- Smart migration templates
- Population migrations
- Migration batches and rollback
- Dry-run mode
- Migration checksums
- Modified migration detection
- Repair tools
- Schema squash and unsquash
- PDO, callable and array-based connections
- MySQL, MariaDB, PostgreSQL and SQLite support

Start with [Getting Started](getting-started.md).
