<?php

namespace Eril\Migraw;

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
}
