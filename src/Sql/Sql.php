<?php

namespace Eril\Migraw\Sql;

/**
 * Lightweight schema statement factory.
 *
 * Sql provides the preferred fluent API used by Migration helper methods.
 * @deprecated v0.1 
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
}
