<?php

namespace Eril\Migraw;

use Eril\Migraw\Sql\Delete;
use Eril\Migraw\Sql\Insert;
use Eril\Migraw\Sql\RawSql;
use Eril\Migraw\Sql\SqlStatement;

/**
 * Base class for all seeders.
 *
 * Seeders are intended for inserting or updating
 * initial application data.
 */
abstract class Seeder
{
    /**
     * Execute the seeder.
     *
     * The returned value may be:
     * - a raw SQL string
     * - an array of SQL statements
     * - a SqlStatement instance
     *
     * @return string|array<string|SqlStatement>|SqlStatement
     */
    abstract public function run(): string|array|SqlStatement;

    /**
     * Create a new INSERT statement.
     *
     * @param string $table Target table name.
     */
    protected function insert(
        string $table
    ): Insert {
        return new Insert($table);
    }

    /**
     * Create a new INSERT IGNORE statement.
     *
     * Useful for idempotent seeders.
     *
     * @param string $table Target table name.
     */
    protected function insertIgnore(
        string $table
    ): Insert {
        return (new Insert($table))
            ->ignore();
    }

    /**
     * Create a new INSERT ... ON DUPLICATE KEY UPDATE statement.
     *
     * @param string $table Target table name.
     * @param string[] $columns Columns to update when a duplicate key is found.
     */
    protected function insertOrUpdate(
        string $table,
        array $columns
    ): Insert {
        return (new Insert($table))
            ->onDuplicateUpdate($columns);
    }

    /**
     * Create a DELETE statement.
     *
     * A WHERE clause is required unless ->all()
     * is explicitly called.
     *
     * @param string $table Target table name.
     */
    protected function delete(
        string $table
    ): Delete {
        return new Delete($table);
    }

    /**
     * Create a DELETE statement affecting all rows.
     *
     * @param string $table Target table name.
     */
    protected function deleteAll(
        string $table
    ): Delete {
        return (new Delete($table))
            ->all();
    }

}