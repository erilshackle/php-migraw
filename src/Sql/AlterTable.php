<?php

namespace Eril\Migraw\Sql;

class AlterTable implements SqlStatement
{
    protected array $operations = [];

    public function __construct(
        protected string $table
    ) {}

    public function add(string $definition): static
    {
        $this->operations[] = 'ADD COLUMN ' . trim($definition);

        return $this;
    }

    public function modify(string $definition): static
    {
        $this->operations[] = 'MODIFY COLUMN ' . trim($definition);

        return $this;
    }

    public function rename(string $from, string $to): static
    {
        $this->operations[] = "RENAME COLUMN {$from} TO {$to}";

        return $this;
    }

    public function drop(string $field): static
    {
        $this->operations[] = "DROP COLUMN {$field}";

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
