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
}
