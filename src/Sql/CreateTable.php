<?php

namespace Eril\SqlMigrator\Sql;

class CreateTable implements SqlStatement
{
    protected array $fields = [];

    protected array $constraints = [];

    protected bool $ifNotExists = false;

    public function __construct(
        protected string $table
    ) {}

    public function ifNotExists(): static
    {
        $this->ifNotExists = true;

        return $this;
    }

    public function field(string $definition): static
    {
        $this->fields[] = trim($definition);

        return $this;
    }

    public function constraint(string $definition): static
    {
        $this->constraints[] = trim($definition);

        return $this;
    }

    public function toSql(): string
    {
        $parts = array_merge($this->fields, $this->constraints);

        if ($parts === []) {
            throw new \RuntimeException("Cannot create table {$this->table} without fields or constraints.");
        }

        $body = implode(",\n    ", $parts);

        $ifNotExists = $this->ifNotExists ? 'IF NOT EXISTS ' : '';

        return "CREATE TABLE {$ifNotExists}{$this->table} (\n    {$body}\n);";
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}
