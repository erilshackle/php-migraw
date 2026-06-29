<?php

namespace Eril\Migraw\Schema;

use Eril\Migraw\Sql\SqlStatement;
use InvalidArgumentException;

/**
 * DROP TABLE statement builder.
 */
class DropTable implements SqlStatement
{
    /**
     * Whether to include IF EXISTS.
     */
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
     * Add IF EXISTS to the DROP TABLE statement.
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
