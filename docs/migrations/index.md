# Migrations

A Migraw migration represents a database change with an `up()` and `down()` operation.

```php
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        // Apply the change.
    }

    public function down(): string|array|SqlStatement
    {
        // Reverse the change.
    }
};
```

Migraw supports two main styles:

- **Raw SQL** — write SQL directly with `$this->raw()`.
- **Fluent** — use Migraw's SQL statement helpers.

The default generated style is controlled by:

```php
'template' => 'raw',
```

Use population migrations when the migration exists to insert stable application data rather than change the schema.

See:

- [Raw SQL](raw.md)
- [Fluent](fluent.md)
- [Population](population.md)
