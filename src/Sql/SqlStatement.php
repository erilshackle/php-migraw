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
     * Some statements may generate different SQL
     * depending on the target database driver.
     *
     * @param string|null $driver
     *
     * Supported drivers:
     * - mysql
     * - mariadb
     * - sqlite
     * - pgsql
     *
     * @return string
     */
    public function toSql(
        ?string $driver = null
    ): string;

    /**
     * Convert the statement to SQL.
     *
     * @return string
     */
    public function __toString(): string;
}
