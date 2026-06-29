<?php

namespace Eril\Migraw\Sql;

use InvalidArgumentException;

/**
 * Low-level DROP TABLE SQL helper.
 */
class DropTable implements SqlStatement
{
    protected bool $ifExists = false;

    /**
     * @param string $table Table name.
     */
    public function __construct(
        protected string $table
    ) {
        if (trim($table) === '') {
            throw new InvalidArgumentException('Table name cannot be empty.');
        }
    }

    /**
     * Add IF EXISTS.
     *
     * @return static
     */
    public function ifExists(): static
    {
        $this->ifExists = true;

        return $this;
    }

    /**
     * Convert the DROP TABLE statement to SQL.
     *
     * @param string|null $driver PDO driver name. Currently unused.
     *
     * @return string
     */
    public function toSql(?string $driver = null): string
    {
        $ifExists = $this->ifExists ? 'IF EXISTS ' : '';

        return "DROP TABLE {$ifExists}{$this->table};";
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
