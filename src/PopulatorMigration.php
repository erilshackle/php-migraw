<?php

namespace Eril\Migraw;

use Eril\Migraw\Sql\Populate;
use Eril\Migraw\Sql\Sql;

/**
 * Base class for migrations that populate required application data.
 *
 * Populator migrations participate in the normal Migraw migration history,
 * batching and status flow. Their populated data is preserved during rollback
 * by default.
 *
 * Override down() when the inserted data can be safely removed.
 */
abstract class PopulatorMigration extends Migration
{
    /**
     * Preserve populated data during rollback by default.
     *
     * The migration record is still removed from the repository, allowing the
     * populator to run again on a future migrate operation. For that reason,
     * population statements should always be idempotent.
     *
     * @return array<int,never>
     */
    public function down(): array
    {
        return [];
    }

    /**
     * Create an idempotent data population statement.
     *
     * Existing records are detected through a PRIMARY KEY or UNIQUE constraint
     * corresponding to `$uniqueBy`.
     *
     * When `$updateColumns` is empty, conflicting records remain unchanged.
     * Otherwise, only the specified columns are updated.
     *
     * @param string                         $table Target table.
     * @param array<int,array<string,mixed>> $rows Rows to populate.
     * @param string|array<int,string>       $uniqueBy Conflict columns.
     * @param array<int,string>              $updateColumns Columns updated on conflict.
     *
     * @return Populate
     */
    final protected function populate(
        string $table,
        array $rows,
        string|array $uniqueBy,
        array $updateColumns = []
    ): Populate {
        return Sql::populate(
            $table,
            $rows,
            $uniqueBy,
            $updateColumns
        );
    }
}