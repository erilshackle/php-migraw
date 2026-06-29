<?php

namespace Eril\Migraw\Sql;

/**
 * Low-level SQL statement factory.
 *
 * Sql is kept as a compatibility layer for users who prefer the older helper
 * style. New migrations should usually use the helper methods available inside
 * Migration: $this->create(), $this->alter(), $this->drop(), $this->rename().
 */
final class Sql
{
    /**
     * Create a low-level CREATE TABLE statement.
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
     * Create a low-level ALTER TABLE statement.
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
     * Create a low-level RENAME TABLE statement.
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
     * Create a low-level DROP TABLE statement.
     *
     * @param string $table Table name.
     *
     * @return DropTable
     */
    public static function drop(string $table): DropTable
    {
        return new DropTable($table);
    }
}
