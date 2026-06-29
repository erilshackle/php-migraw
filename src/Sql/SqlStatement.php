<?php

namespace Eril\Migraw\Sql;

/**
 * Represents a SQL statement that can be converted into executable SQL.
 */
interface SqlStatement
{
    /**
     * Convert the statement to SQL.
     *
     * Some statements may generate different SQL depending on the database
     * driver. Statements that do not need driver-specific behavior may ignore
     * the argument.
     *
     * @param string|null $driver PDO driver name, such as mysql, sqlite or pgsql.
     *
     * @return string
     */
    public function toSql(?string $driver = null): string;

    /**
     * Convert the statement to SQL using the default driver-agnostic output.
     *
     * @return string
     */
    public function __toString(): string;
}
