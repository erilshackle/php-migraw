<?php

namespace Eril\Migraw\Schema;

use Eril\Migraw\Sql\SqlStatement;
use InvalidArgumentException;
use RuntimeException;

/**
 * RENAME TABLE statement builder.
 */
class RenameTable implements SqlStatement
{
    /**
     * New table name.
     */
    protected ?string $newName = null;

    /**
     * @param string $table Current table name.
     */
    public function __construct(
        protected string $table
    ) {
        if (trim($table) === '') {
            throw new InvalidArgumentException('Table name cannot be empty.');
        }
    }

    /**
     * Set the new table name.
     *
     * @param string $newName New table name.
     *
     * @return static
     */
    public function to(string $newName): static
    {
        $newName = trim($newName);

        if ($newName === '') {
            throw new InvalidArgumentException('New table name cannot be empty.');
        }

        $this->newName = $newName;

        return $this;
    }

    /**
     * Convert the RENAME TABLE statement to SQL.
     *
     * @param string|null $driver PDO driver name. Currently unused.
     *
     * @return string
     */
    public function toSql(?string $driver = null): string
    {
        if (! $this->newName) {
            throw new RuntimeException("New table name is required to rename {$this->table}.");
        }

        return "RENAME TABLE {$this->table} TO {$this->newName};";
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
