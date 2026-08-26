# Fluent Migrations

Migraw's fluent API keeps common schema operations concise while keeping SQL definitions visible.

Set fluent as the default generated template:

```php
'template' => 'fluent',
```

Then create migrations normally:

```bash
php vendor/bin/migraw make create_users_table
```

## Create a table

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
            ->column('email VARCHAR(180) NOT NULL')
            ->unique('uq_users_email', 'email')
            ->timestamps();
    }

    public function down(): SqlStatement
    {
        return $this->drop('users')->ifExists();
    }
};
```

## CreateTable methods

| Method | Purpose |
| --- | --- |
| `ifNotExists()` | Adds `IF NOT EXISTS` |
| `id($definition = 'id')` | Adds a conventional or custom primary-key definition |
| `column($definition)` | Adds a SQL column definition |
| `timestamps()` | Adds `created_at` and `updated_at` |
| `softDeletes($name = 'deleted_at')` | Adds a nullable soft-delete timestamp |
| `primary($columns, $name = null)` | Adds a primary-key constraint |
| `unique($name, $columns)` | Adds a unique constraint |
| `index($name, $columns)` | Adds an index |
| `foreign(...)` | Adds a foreign key |
| `constraint($definition)` | Adds a table-level constraint |
| `raw($definition)` | Adds a raw table definition |

### `id()`

Default:

```php
$this->create('users')
    ->id();
```

Custom column name:

```php
$this->create('users')
    ->id('user_id');
```

UUID shorthand:

```php
$this->create('users')
    ->id('uuid');
```

Custom SQL definition:

```php
$this->create('users')
    ->id('uuid CHAR(36) PRIMARY KEY');
```

Migraw compiles supported shorthand according to the active database driver.

### Columns

Column definitions are SQL fragments:

```php
$this->create('products')
    ->id()
    ->column('name VARCHAR(255) NOT NULL')
    ->column('price DECIMAL(10,2) NOT NULL DEFAULT 0')
    ->column('active TINYINT(1) NOT NULL DEFAULT 1');
```

### Timestamps

```php
$this->create('posts')
    ->id()
    ->timestamps();
```

Adds conventional `created_at` and `updated_at` columns using driver-aware SQL.

### Soft deletes

```php
$this->create('posts')
    ->id()
    ->softDeletes();
```

Custom name:

```php
->softDeletes('removed_at')
```

### Primary keys

Single column:

```php
->primary('id')
```

Composite primary key:

```php
->primary(['tenant_id', 'user_id'])
```

Named constraint:

```php
->primary(['tenant_id', 'user_id'], 'pk_tenant_user')
```

### Unique constraints

```php
->unique('uq_users_email', 'email')
```

Composite:

```php
->unique(
    'uq_membership',
    ['user_id', 'organization_id']
)
```

### Indexes

```php
->index('idx_users_status', 'status')
```

Composite:

```php
->index(
    'idx_orders_customer_status',
    ['customer_id', 'status']
)
```

### Foreign keys

Basic:

```php
->foreign('role_id', 'roles')
```

Custom referenced column:

```php
->foreign(
    'country_code',
    'countries',
    'code'
)
```

Named constraint with actions:

```php
->foreign(
    columns: 'user_id',
    referencesTable: 'users',
    referencesColumns: 'id',
    name: 'fk_posts_user',
    onDelete: 'CASCADE',
    onUpdate: 'CASCADE'
)
```

Composite foreign key:

```php
->foreign(
    ['tenant_id', 'user_id'],
    'tenant_users',
    ['tenant_id', 'user_id'],
    'fk_orders_tenant_user'
)
```

### Custom constraints

```php
->constraint('CHECK (price >= 0)')
```

### Raw table definition

Use `raw()` inside a fluent table builder for database-specific definitions:

```php
$this->create('articles')
    ->id()
    ->column('title VARCHAR(255) NOT NULL')
    ->raw('FULLTEXT INDEX ft_articles_title (title)');
```

## Alter a table

```php
public function up(): SqlStatement
{
    return $this->alter('users')
        ->add('phone VARCHAR(50) NULL')
        ->add('active TINYINT(1) NOT NULL DEFAULT 1');
}
```

`column()` and `add()` both add a column:

```php
$this->alter('users')
    ->column('phone VARCHAR(50) NULL');
```

```php
$this->alter('users')
    ->add('phone VARCHAR(50) NULL');
```

## AlterTable methods

| Method | Purpose |
| --- | --- |
| `column($definition)` | Add a column |
| `add($definition)` | Alias-style helper for adding a column |
| `modify($definition)` | Modify an existing column |
| `renameColumn($from, $to)` | Rename a column |
| `dropColumn($name)` | Drop a column |
| `index($name, $columns)` | Add an index |
| `unique($name, $columns)` | Add a unique constraint |
| `foreign(...)` | Add a foreign key |
| `dropIndex($name)` | Drop an index |
| `dropForeign($name)` | Drop a foreign-key constraint |
| `raw($operation)` | Add a raw `ALTER TABLE` operation |

### Modify a column

```php
$this->alter('users')
    ->modify('phone VARCHAR(80) NOT NULL');
```

### Rename a column

```php
$this->alter('users')
    ->renameColumn('name', 'full_name');
```

Rollback:

```php
$this->alter('users')
    ->renameColumn('full_name', 'name');
```

### Drop a column

```php
$this->alter('users')
    ->dropColumn('phone');
```

### Add an index

```php
$this->alter('users')
    ->index('idx_users_email', 'email');
```

Composite:

```php
$this->alter('orders')
    ->index(
        'idx_orders_customer_status',
        ['customer_id', 'status']
    );
```

### Add a unique constraint

```php
$this->alter('users')
    ->unique('uq_users_email', 'email');
```

### Add a foreign key

```php
$this->alter('posts')
    ->foreign(
        columns: 'user_id',
        referencesTable: 'users',
        referencesColumns: 'id',
        name: 'fk_posts_user',
        onDelete: 'CASCADE'
    );
```

### Drop indexes and foreign keys

```php
$this->alter('users')
    ->dropIndex('idx_users_email');
```

```php
$this->alter('posts')
    ->dropForeign('fk_posts_user');
```

### Raw alter operation

```php
$this->alter('products')
    ->raw('ADD CHECK (price >= 0)');
```

## Drop a table

```php
$this->drop('users');
```

Safer rollback-style usage:

```php
$this->drop('users')->ifExists();
```

## Rename a table

```php
$this->rename('users')
    ->to('members');
```

Rollback:

```php
$this->rename('members')
    ->to('users');
```

## Multiple fluent statements

Return an array:

```php
public function up(): array
{
    return [
        $this->create('roles')
            ->id()
            ->column('name VARCHAR(100) NOT NULL UNIQUE'),

        $this->create('users')
            ->id()
            ->column('role_id INT NOT NULL')
            ->column('name VARCHAR(255) NOT NULL')
            ->foreign('role_id', 'roles'),
    ];
}
```

Rollback in reverse dependency order:

```php
public function down(): array
{
    return [
        $this->drop('users')->ifExists(),
        $this->drop('roles')->ifExists(),
    ];
}
```

## Mix fluent and raw

You can mix both styles:

```php
public function up(): array
{
    return [
        $this->alter('users')
            ->add('search_name VARCHAR(255) NULL'),

        $this->raw(<<<'SQL'
CREATE INDEX idx_users_search_name
ON users (search_name);
SQL),
    ];
}
```

Use fluent helpers for supported common operations and raw SQL when the database-specific statement is clearer.
