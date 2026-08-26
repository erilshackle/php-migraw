# Population Migrations

Population migrations insert deterministic data required by the application.

Common examples:

- roles
- permissions
- countries or islands
- fixed categories
- default statuses
- system configuration

Create one with:

```bash
php vendor/bin/migraw make populate_roles --populate
```

A population migration extends `PopulatorMigration` and implements `population()`.

```php
<?php

use Eril\Migraw\PopulatorMigration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends PopulatorMigration
{
    public function population(): string|array|SqlStatement
    {
        return $this->populate(
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

## Arguments

`populate()` accepts:

```php
$this->populate(
    string $table,
    array $rows,
    string|array $uniqueBy,
    array $updateColumns = []
)
```

| Argument | Description |
| --- | --- |
| `$table` | Target table |
| `$rows` | Rows to insert |
| `$uniqueBy` | Column(s) identifying an existing row |
| `$updateColumns` | Optional columns updated on conflict |

The columns used by `uniqueBy` must correspond to a `PRIMARY KEY` or `UNIQUE` constraint.

## Preserve existing rows

With no update columns, conflicts preserve the existing row:

```php
return $this->populate(
    'roles',
    $rows,
    uniqueBy: 'slug'
);
```

## Update existing rows

Pass update columns directly:

```php
return $this->populate(
    'roles',
    [
        [
            'slug' => 'admin',
            'name' => 'System Administrator',
            'active' => true,
        ],
    ],
    uniqueBy: 'slug',
    updateColumns: [
        'name',
        'active',
    ]
);
```

Or use `update()`:

```php
return $this->populate(
    'roles',
    $rows,
    uniqueBy: 'slug'
)->update([
    'name',
    'active',
]);
```

A single column is also valid:

```php
->update('name')
```

## Composite conflict keys

```php
return $this->populate(
    'permissions',
    $rows,
    uniqueBy: [
        'resource',
        'action',
    ]
);
```

## Multiple population statements

```php
public function population(): array
{
    return [
        $this->populate(
            'roles',
            $roles,
            uniqueBy: 'slug'
        ),

        $this->populate(
            'permissions',
            $permissions,
            uniqueBy: ['resource', 'action']
        ),
    ];
}
```

## Rollback behavior

`PopulatorMigration` does not remove populated data during normal rollback.

Its default `down()` is empty. Rolling it back removes the migration record so the population can run again later.

## Driver support

Population statements currently compile for:

- MySQL / MariaDB
- PostgreSQL
- SQLite
