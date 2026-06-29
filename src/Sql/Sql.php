<?php

namespace Eril\Migraw\Sql;

final class Sql
{
    public static function create(string $table, $ifNotExists = false): CreateTable
    {
        $t = new CreateTable($table);
        if ($ifNotExists) {
            $t->ifNotExists();
        }
        return $t;
    }

    public static function alter(string $table): AlterTable
    {
        return new AlterTable($table);
    }

    public static function rename(string $table): RenameTable
    {
        return new RenameTable($table);
    }

    public static function drop(string $table, $ifExists = false): DropTable
    {
        $t = new DropTable($table);
        if ($ifExists) {
            $t->ifExists();
        }
        return $t;
    }
}
