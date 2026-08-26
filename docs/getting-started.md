# Getting Started

## Requirements

- PHP 8.2+
- PDO
- A supported PDO database driver

## Installation

```bash
composer require eril/migraw
```

## Initialize

For MySQL/MariaDB:

```bash
php vendor/bin/migraw init
```

or:

```bash
php vendor/bin/migraw init:mysql
```

For PostgreSQL:

```bash
php vendor/bin/migraw init:pgsql
```

For SQLite:

```bash
php vendor/bin/migraw init:sqlite
```

This creates `migraw.php` and, when necessary, the migrations directory.
you can also use `--force` to overwrite the current `migraw.php` file.

## Create your first migration

```bash
php vendor/bin/migraw make create_users_table
```

By default Migraw generates the template configured in `migraw.php`.

To run migrations:

```bash
php vendor/bin/migraw migrate
```

You can also use:

```bash
php vendor/bin/migraw up
```

## Check status

```bash
php vendor/bin/migraw status
```

## Roll back

```bash
php vendor/bin/migraw rollback
```

or:

```bash
php vendor/bin/migraw down
```

## Preview SQL

```bash
php vendor/bin/migraw migrate --dry-run
```

`--pretend` is also supported.

## Next

- [Configuration](configuration.md)
- [Raw migrations](migrations/raw.md)
- [Fluent migrations](migrations/fluent.md)
- [Population migrations](migrations/population.md)
- [Commands](commands/index.md)
