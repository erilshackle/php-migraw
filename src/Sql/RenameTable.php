<?php

namespace Eril\Migraw\Sql;

class RenameTable implements SqlStatement
{
    protected ?string $newName = null;

    public function __construct(
        protected string $table
    ) {}

    public function to(string $newName): static
    {
        $this->newName = $newName;

        return $this;
    }

    public function toSql(?string $driver = null): string
    {
        if (! $this->newName) {
            throw new \RuntimeException("New table name is required to rename {$this->table}.");
        }

        return "RENAME TABLE {$this->table} TO {$this->newName};";
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}
