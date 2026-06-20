<?php

namespace Eril\Migraw;

use Eril\Migraw\Sql\Sql;
use Eril\Migraw\Sql\SqlStatement;

abstract class Migration
{
    /**
     * SQL executado ao aplicar a migration.
     */
    abstract public function up(): string|array|SqlStatement;

    /**
     * SQL executado ao reverter a migration.
     */
    abstract public function down(): string|array|SqlStatement;

    protected function createTable(String $table, bool $ifNotExists = false)
    {
        return Sql::create($table, $ifNotExists);
    }

    protected function alterTable(String $table)
    {
        return Sql::alter($table);
    }

    protected function dropTable(String $table, bool $ifExists = false)
    {
        return Sql::drop($table, $ifExists);
    }

    protected function renameTable(String $table)
    {
        return Sql::rename($table);
    }
}
