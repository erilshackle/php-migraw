<?php

namespace Eril\Migraw\Sql;

use InvalidArgumentException;

/**
 * Raw SQL statement wrapper.
 */
class RawSql implements SqlStatement
{
    /**
     * @param string $sql Raw SQL.
     */
    public function __construct(
        protected string $sql
    ) {
        if (trim($sql) === '') {
            throw new InvalidArgumentException('Raw SQL cannot be empty.');
        }
    }

    /**
     * Convert the raw SQL statement to SQL.
     *
     * @param string|null $driver PDO driver name.
     *
     * @return string
     */
    public function toSql(?string $driver = null): string
    {
        return $this->sql;
    }

    /**
     * Convert the statement to SQL.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toSql();
    }
}