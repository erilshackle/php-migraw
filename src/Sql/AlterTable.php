<?php

namespace Eril\Migraw\Sql;

/**
 * ALTER TABLE statement builder.
 */
class AlterTable implements SqlStatement
{
    protected array $operations = [];

    public function __construct(
        protected string $table
    ) {}

    /**
     * Add a column or constraint.
     *
     * @param string $definition
     *
     * @return static
     */
    public function add(string $definition): static
    {
        $this->operations[] = 'ADD COLUMN ' . trim($definition);

        return $this;
    }

    /**
     * Modify a column definition.
     *
     * @param string $definition
     *
     * @return static
     */
    public function modify(string $definition): static
    {
        $this->operations[] = 'MODIFY COLUMN ' . trim($definition);

        return $this;
    }

    /**
     * Rename a column.
     *
     * @param string $column
     * @param string $newName
     *
     * @return static
     */
    public function rename(string $column, string $newName): static
    {
        $this->operations[] = "RENAME COLUMN {$column} TO {$newName}";

        return $this;
    }

    /**
     * Drop a column, index or constraint.
     *
     * @param string $column
     *
     * @return static
     */
    public function drop(string $column): static
    {
        $this->operations[] = "DROP COLUMN {$column}";

        return $this;
    }

    public function constraint(string $definition): static
    {
        $this->operations[] = 'ADD CONSTRAINT ' . trim($definition);

        return $this;
    }

    public function raw(string $definition): static
    {
        $this->operations[] = trim($definition);

        return $this;
    }

    public function toSql(): string
    {
        if ($this->operations === []) {
            throw new \RuntimeException("Cannot alter table {$this->table} without operations.");
        }

        $body = implode(",\n    ", $this->operations);

        return "ALTER TABLE {$this->table}\n    {$body};";
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}
