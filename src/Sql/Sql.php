<?php

namespace Eril\Migraw\Sql;

final class Sql
{
    public static function create(string $table, bool $ifNotExists = false): CreateTable
    {
        $statement = new CreateTable($table);

        if ($ifNotExists) {
            $statement->ifNotExists();
        }

        return $statement;
    }

    public static function alter(string $table): AlterTable
    {
        return new AlterTable($table);
    }

    public static function rename(string $table): RenameTable
    {
        return new RenameTable($table);
    }

    public static function drop(string $table, bool $ifExists = false): DropTable
    {
        $statement = new DropTable($table);

        if ($ifExists) {
            $statement->ifExists();
        }

        return $statement;
    }

    public static function insert(string $table, bool $ignore = false): Insert
    {
        $statement = new Insert($table);

        if ($ignore) {
            $statement->ignore();
        }

        return $statement;
    }

    public static function delete(string $table): Delete
    {
        return new Delete($table);
    }
}