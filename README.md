# SqlMigrator

Simple SQL-first migrations for PHP.

SqlMigrator is a small migration library focused on explicit SQL.  
You can write raw SQL directly or use the optional fluent SQL helper to assemble basic SQL statements.

## Installation

```bash
composer require eril/sql-migrator
```

## Configuration

Create a `sql-migrator.php` file in your project root:

```php
<?php

use PDO;

return [
    'path' => __DIR__ . '/database/migrations',
    'table' => 'migrations',

    'pdo' => function (): PDO {
        return new PDO(
            'mysql:host=localhost;dbname=app;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    },
];
```

## Creating a migration

```bash
php vendor/bin/sql-migrator make create_users_table
```

This creates a file inside `database/migrations`.

## Raw SQL migration

```php
<?php

use Eril\SqlMigrator\Migration;

return new class extends Migration
{
    public function up(): string|array
    {
        return <<<SQL
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        );
        SQL;
    }

    public function down(): string|array
    {
        return <<<SQL
        DROP TABLE users;
        SQL;
    }
};
```

## Assisted SQL migration

```php
<?php

use Eril\SqlMigrator\Migration;
use Eril\SqlMigrator\Sql\Sql;
use Eril\SqlMigrator\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return Sql::create('users', ifNotExists: false)
            ->ifNotExists()
            ->field('id INT AUTO_INCREMENT PRIMARY KEY')
            ->field('name VARCHAR(120) NOT NULL')
            ->field('email VARCHAR(160) NOT NULL UNIQUE')
            ->field('password VARCHAR(255) NOT NULL')
            ->field('created_at TIMESTAMP NULL')
            ->field('updated_at TIMESTAMP NULL');
    }

    public function down(): string|array|SqlStatement
    {
        return Sql::drop('users', ifxists: false)->ifExists();
    }
};
```

## Multiple SQL statements

```php
public function up(): string|array|SqlStatement
{
    return [
        Sql::create('roles')
            ->field('id INT AUTO_INCREMENT PRIMARY KEY')
            ->field('name VARCHAR(80) NOT NULL UNIQUE'),

        Sql::create('users')
            ->field('id INT AUTO_INCREMENT PRIMARY KEY')
            ->field('role_id INT NULL')
            ->field('name VARCHAR(120) NOT NULL')
            ->constraint('FOREIGN KEY (role_id) REFERENCES roles(id)'),
    ];
}
```

## Commands

Run pending migrations:

```bash
php vendor/bin/sql-migrator migrate
```

Rollback the last batch:

```bash
php vendor/bin/sql-migrator rollback
```

Show migration status:

```bash
php vendor/bin/sql-migrator status
```

Rollback all migrations:

```bash
php vendor/bin/sql-migrator reset
```

Rollback all migrations and run them again:

```bash
php vendor/bin/sql-migrator refresh
```

Create a migration:

```bash
php vendor/bin/sql-migrator make create_users_table
```

## SQL helper

### Create table

```php
Sql::create('users')
    ->field('id INT AUTO_INCREMENT PRIMARY KEY')
    ->field('name VARCHAR(255) NOT NULL')
    ->constraint('UNIQUE(name)');
```

### Alter table

```php
Sql::alter('users')
    ->add('phone VARCHAR(50) NULL')
    ->modify('name VARCHAR(180) NOT NULL')
    ->rename('name', 'full_name')
    ->drop('old_column');
```

### Rename table

```php
Sql::rename('users')
    ->to('members');
```

### Drop table

```php
Sql::drop('users')
    ->ifExists();
```

## Philosophy

SqlMigrator does not try to infer your schema.

It does not diff databases, inspect tables, guess column types, or hide SQL behind a complex DSL.

You can write SQL directly, or use the helper when you only want a small amount of structure.

## Transactions

SqlMigrator uses schema transactions automatically for drivers where they are generally reliable, such as SQLite and PostgreSQL.

For MySQL and MariaDB, schema transactions are not enabled by default because many DDL statements cause implicit commits.

## Testing

```bash
composer install
composer test
```

## Requirements

* PHP 8.1+
* PDO

## License

MIT
