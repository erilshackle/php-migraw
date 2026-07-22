<?php

namespace Eril\Migraw;

use Eril\Migraw\Sql\AlterTable;
use Eril\Migraw\Sql\CreateTable;
use Eril\Migraw\Sql\DropTable;
use Eril\Migraw\Sql\Populate;
use Eril\Migraw\Sql\RawSql;
use Eril\Migraw\Sql\RenameTable;
use Eril\Migraw\Sql\SqlStatement;

/**
 * Base class for all Migraw migrations.
 *
 * A migration may return raw SQL strings, SqlStatement instances, or arrays
 * containing both. The helper methods provide the preferred lightweight schema
 * API for table operations.
 */
abstract class Migration
{
    /**
     * SQL executed when the migration is applied.
     *
     * @return string|SqlStatement|array<int,string|SqlStatement>
     */
    abstract public function up(): string|SqlStatement|array;

    /**
     * SQL executed when the migration is rolled back.
     *
     * @return string|SqlStatement|array<int,string|SqlStatement>
     */
    abstract public function down(): string|SqlStatement|array;


    /**
     * Wrap raw SQL as a Migraw SQL statement.
     *
     * Example:
     *
     * ```
     * return $this->raw(<<<SQL
     * CREATE TABLE users (
     *     id INT PRIMARY KEY
     * );
     * SQL);
     * ```
     *
     * @param string $SQL Raw SQL.
     *
     * @return RawSql
     */
    final protected function raw(string $SQL): RawSql
    {
        return new RawSql($SQL);
    }

    /**
     * Create a CREATE TABLE schema statement.
     *
     * @param string $table Table name.
     *
     * @return CreateTable
     */
    final protected function create(string $table): CreateTable
    {
        return new CreateTable($table);
    }

    /**
     * Create an ALTER TABLE schema statement.
     *
     * @param string $table Table name.
     *
     * @return AlterTable
     */
    final protected function alter(string $table): AlterTable
    {
        return new AlterTable($table);
    }

    /**
     * Create a DROP TABLE schema statement.
     *
     * @param string $table Table name.
     *
     * @return DropTable
     */
    final protected function drop(string $table): DropTable
    {
        return new DropTable($table);
    }

    /**
     * Create a RENAME TABLE schema statement.
     *
     * @param string $table Current table name.
     *
     * @return RenameTable
     */
    final protected function rename(string $table): RenameTable
    {
        return new RenameTable($table);
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
        return new Populate(
            $table,
            $rows,
            $uniqueBy,
            $updateColumns
        );
    }
}
