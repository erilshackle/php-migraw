# Raw SQL Migrations

Raw migrations keep SQL completely explicit.

Set raw as the default template:

```php
'template' => 'raw',
```

## Create table

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

## Alter table

```php
public function up(): string|array|SqlStatement
{
    return $this->raw(<<<'SQL'
    ALTER TABLE users
        ADD COLUMN phone VARCHAR(50) NULL;
    SQL);
}
```

Rollback:

```php
public function down(): string|array|SqlStatement
{
    return $this->raw(<<<'SQL'
    ALTER TABLE users
        DROP COLUMN phone;
    SQL);
}
```

## Multiple statements

Return an array when you want Migraw to execute multiple independent statements in order:

```php
public function up(): array
{
    return [
        $this->raw(<<<'SQL'
        ALTER TABLE users
            ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1;
        SQL),

        $this->raw(<<<'SQL'
        CREATE INDEX idx_users_active
        ON users (active);
        SQL),
    ];
}
```

Rollback in reverse order:

```php
public function down(): array
{
    return [
        $this->raw(<<<'SQL'
        DROP INDEX idx_users_active ON users;
        SQL),

        $this->raw(<<<'SQL'
        ALTER TABLE users
            DROP COLUMN active;
        SQL),
    ];
}
```

## Database-specific SQL

Raw migrations are useful for statements specific to the target database:

```php
return $this->raw(<<<'SQL'
CREATE FULLTEXT INDEX ft_articles_body
ON articles (body);
SQL);
```

You can use triggers, procedures, advanced constraints, expressions, indexes, and other SQL features supported by your database.

## Force a blank raw migration

Even if the configured default template is fluent:

```bash
php vendor/bin/migraw make custom_change --sql
```

Use raw when direct SQL is the clearest representation of the database change.
