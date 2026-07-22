<?php

namespace Eril\Migraw\Sql;

/**
 * Lightweight schema statement factory.
 *
 * Sql provides the preferred fluent API used by Migration helper methods.
 * @since 1.0.0 
 */
final class Sql
{
    /**
     * Create a CREATE TABLE statement.
     *
     * @param string $table Table name.
     *
     * @return CreateTable
     */
    public static function create(string $table): CreateTable
    {
        return new CreateTable($table);
    }

    /**
     * Create an ALTER TABLE statement.
     *
     * @param string $table Table name.
     *
     * @return AlterTable
     */
    public static function alter(string $table): AlterTable
    {
        return new AlterTable($table);
    }

    /**
     * Create a DROP TABLE statement.
     *
     * @param string $table Table name.
     *
     * @return DropTable
     */
    public static function drop(string $table): DropTable
    {
        return new DropTable($table);
    }

    /**
     * Create a RENAME TABLE statement.
     *
     * @param string $table Current table name.
     *
     * @return RenameTable
     */
    public static function rename(string $table): RenameTable
    {
        return new RenameTable($table);
    }

    /**
     * Create an idempotent data population statement.
     *
     * Existing records are detected through a PRIMARY KEY or UNIQUE constraint
     * corresponding to the columns passed in `$uniqueBy`.
     *
     * When `$updateColumns` is empty, existing rows remain unchanged. Otherwise,
     * the selected columns are updated with the incoming values.
     *
     * @param string                         $table Target table.
     * @param array<int,array<string,mixed>> $rows Rows to populate.
     * @param string|array<int,string>       $uniqueBy Conflict columns.
     * @param array<int,string>              $updateColumns Columns updated on conflict.
     *
     * @return Populate
     */
    public static function populate(
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
