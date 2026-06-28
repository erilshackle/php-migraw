<?php

namespace Eril\Migraw;

use Eril\Migraw\Schema\AlterTable;
use Eril\Migraw\Schema\CreateTable;
use Eril\Migraw\Schema\DropTable;
use Eril\Migraw\Schema\RenameTable;
use Eril\Migraw\Schema\Schema;
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
     * @param bool $ifNotExists Whether to include IF NOT EXISTS.
     *
     * @return CreateTable
     */
    final protected function create(string $table, bool $ifNotExists = false): CreateTable
    {
        return Schema::create($table, $ifNotExists);
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
        return Schema::alter($table);
    }

    /**
     * Create a DROP TABLE schema statement.
     *
     * @param string $table Table name.
     * @param bool $ifExists Whether to include IF EXISTS.
     *
     * @return DropTable
     */
    final protected function drop(string $table, bool $ifExists = false): DropTable
    {
        return Schema::drop($table, $ifExists);
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
        return Schema::rename($table);
    }

    /**
     * Backwards-compatible alias for create().
     *
     * @param string $table Table name.
     * @param bool $ifNotExists Whether to include IF NOT EXISTS.
     *
     * @return CreateTable
     */
    final protected function createTable(string $table, bool $ifNotExists = false): CreateTable
    {
        return $this->create($table, $ifNotExists);
    }

    /**
     * Backwards-compatible alias for alter().
     *
     * @param string $table Table name.
     *
     * @return AlterTable
     */
    final protected function alterTable(string $table): AlterTable
    {
        return $this->alter($table);
    }

    /**
     * Backwards-compatible alias for drop().
     *
     * @param string $table Table name.
     * @param bool $ifExists Whether to include IF EXISTS.
     *
     * @return DropTable
     */
    final protected function dropTable(string $table, bool $ifExists = false): DropTable
    {
        return $this->drop($table, $ifExists);
    }

    /**
     * Backwards-compatible alias for rename().
     *
     * @param string $table Current table name.
     *
     * @return RenameTable
     */
    final protected function renameTable(string $table): RenameTable
    {
        return $this->rename($table);
    }
}
