<?php

namespace Eril\Migraw\Sql;

/**
 * Represents a SQL statement that can be converted
 * into executable SQL.
 */
interface SqlStatement
{
    /**
     * Convert the statement to SQL.
     *
     * @return string
     */
    public function toSql(): string;

    /**
     * Convert the statement to SQL.
     *
     * @return string
     */
    public function __toString(): string;
}
