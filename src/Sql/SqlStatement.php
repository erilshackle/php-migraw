<?php

namespace Eril\Migraw\Sql;


interface SqlStatement
{
    public function toSql(): string;
}
