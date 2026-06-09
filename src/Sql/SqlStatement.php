<?php

namespace Eril\SqlMigrator\Sql;


interface SqlStatement
{
    public function toSql(): string;
}
