<?php

namespace Eril\Migraw\Sql;

class DropTable implements SqlStatement
{
    protected bool $ifExists = false;

    public function __construct(
        protected string $table
    ) {
        if (trim($table) === '') {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }
    }

    public function ifExists(): static
    {
        $this->ifExists = true;

        return $this;
    }

    public function toSql(): string
    {
        $ifExists = $this->ifExists ? 'IF EXISTS ' : '';

        return "DROP TABLE {$ifExists}{$this->table};";
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}
