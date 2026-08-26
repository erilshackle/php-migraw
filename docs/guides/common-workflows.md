# Common Workflows

## Add a column

```bash
php vendor/bin/migraw make add_phone_to_users
```

Raw example:

```php
return $this->raw(<<<'SQL'
ALTER TABLE users
    ADD COLUMN phone VARCHAR(50) NULL;
SQL);
```

Fluent example:

```php
return $this->alter('users')
    ->add('phone VARCHAR(50) NULL');
```

## Add an index

```php
return $this->alter('users')
    ->index('idx_users_email', 'email');
```

## Add a foreign key

```php
return $this->alter('posts')
    ->foreign(
        columns: 'user_id',
        referencesTable: 'users',
        referencesColumns: 'id',
        name: 'fk_posts_user',
        onDelete: 'CASCADE'
    );
```

## Preview a migration

```bash
php vendor/bin/migraw migrate --dry-run
```

## Undo the latest batch

```bash
php vendor/bin/migraw rollback
```

## Rebuild a development database

```bash
php vendor/bin/migraw fresh
```

## Check for modified migrations

```bash
php vendor/bin/migraw status
```

If an executed migration was intentionally changed:

```bash
php vendor/bin/migraw repair --modified
```

## Compact a long migration history

```bash
php vendor/bin/migraw migrate
php vendor/bin/migraw squash app_schema
```

## Restore the pre-squash migration history

```bash
php vendor/bin/migraw unsquash
```
