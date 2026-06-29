<?php

namespace Eril\Migraw\Sql;

use InvalidArgumentException;
use RuntimeException;

/**
 * Low-level ALTER TABLE SQL helper.
 *
 * This class is kept as a compatibility layer for the older Sql:: API. New
 * migrations should usually prefer the helper methods available on Migration.
 */
class AlterTable implements SqlStatement
{
    /** @var string[] */
    protected array $operations = [];

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
     * Add a column.
     *
     * @param string $definition Column definition.
     *
     * @return static
     */
    public function add(string $definition): static
    {
        return $this->raw('ADD COLUMN ' . trim($definition));
    }

    /**
     * Modify a column.
     *
     * @param string $definition Column definition.
     *
     * @return static
     */
    public function modify(string $definition): static
    {
        return $this->raw('MODIFY COLUMN ' . trim($definition));
    }

    /**
     * Rename a column.
     *
     * @param string $column Current column name.
     * @param string $newName New column name.
     *
     * @return static
     */
    public function rename(string $column, string $newName): static
    {
        return $this->raw("RENAME COLUMN {$column} TO {$newName}");
    }

    /**
     * Drop a column.
     *
     * @param string $column Column name.
     *
     * @return static
     */
    public function drop(string $column): static
    {
        return $this->raw("DROP COLUMN {$column}");
    }

    /**
     * Add a constraint.
     *
     * @param string $definition Constraint definition without ADD CONSTRAINT.
     *
     * @return static
     */
    public function constraint(string $definition): static
    {
        return $this->raw('ADD CONSTRAINT ' . trim($definition));
    }

    /**
     * Add a raw ALTER TABLE operation.
     *
     * @param string $definition Raw operation.
     *
     * @return static
     */
    public function raw(string $definition): static
    {
        $definition = trim($definition);

        if ($definition === '') {
            throw new InvalidArgumentException('Alter operation cannot be empty.');
        }

        $this->operations[] = $definition;

        return $this;
    }

    /**
     * Convert the ALTER TABLE statement to SQL.
     *
     * @param string|null $driver PDO driver name. Currently unused.
     *
     * @return string
     */
    public function toSql(?string $driver = null): string
    {
        if ($this->operations === []) {
            throw new RuntimeException("Cannot alter table {$this->table} without operations.");
        }

        $body = implode(",\n    ", $this->operations);

        return "ALTER TABLE {$this->table}\n    {$body};";
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
