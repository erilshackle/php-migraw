<?php

namespace Eril\Migraw\Sql;

/**
 * SQL statement factory.
 *
 * Provides a lightweight and explicit API for building
 * common schema and data manipulation statements.
 *
 * Examples:
 *
 * Create table:
 *
 * ```php
 * Sql::create('users')
 *     ->field('id INT PRIMARY KEY')
 *     ->field('name VARCHAR(255)');
 * ```
 *
 * Alter table:
 *
 * ```php
 * Sql::alter('users')
 *     ->add('phone VARCHAR(50)');
 * ```
 *
 * Rename table:
 *
 * ```php
 * Sql::rename('users')
 *     ->to('customers');
 * ```
 *
 * Drop table:
 *
 * ```php
 * Sql::drop('users', true);
 * ```
 */
final class Sql
{

    /**
     * Create a CREATE TABLE statement.
     *
     * @see CreateTable
     * @param string $table Table name.
     * @param bool $ifNotExists Whether to add IF NOT EXISTS.
     *
     * @return CreateTable
     */
    public static function create(string $table, bool $ifNotExists = false): CreateTable
    {
        $statement = new CreateTable($table);

        if ($ifNotExists) {
            $statement->ifNotExists();
        }

        return $statement;
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
     * Create a DROP TABLE statement.
     *
     * @param string $table Table name.
     * @param bool $ifExists Whether to add IF EXISTS.
     *
     * @return DropTable
     */
    public static function drop(string $table, bool $ifExists = false): DropTable
    {
        $statement = new DropTable($table);

        if ($ifExists) {
            $statement->ifExists();
        }

        return $statement;
    }
}
