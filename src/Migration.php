<?php

namespace Eril\Migraw;

use Eril\Migraw\Sql\AlterTable;
use Eril\Migraw\Sql\CreateTable;
use Eril\Migraw\Sql\DropTable;
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
}
