<?php

namespace Eril\Migraw;

use Eril\Migraw\Sql\SqlStatement;

/**
 * Base class for idempotent data population migrations.
 *
 * Population migrations participate in the normal migration lifecycle,
 * but do not remove populated data during rollback by default.
 */
abstract class PopulatorMigration extends Migration
{
    /**
     * Define the statements used to populate the database.
     *
     * @return string|SqlStatement|array<int, string|SqlStatement>
     */
    abstract public function population(): string|SqlStatement|array;

    /**
     * Return the population statements to the migrator.
     *
     * @return string|SqlStatement|array<int, string|SqlStatement>
     */
    final public function up(): string|SqlStatement|array
    {
        return $this->population();
    }

    /**
     * Population migrations preserve their data during rollback.
     *
     * Rollback only removes the migration record, allowing the
     * population migration to be executed again later.
     *
     * @return array<int, string|SqlStatement>
     */
    public function down(): array
    {
        return [];
    }
}