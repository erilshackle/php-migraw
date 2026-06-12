<?php

namespace Eril\Migraw\Sql;

use InvalidArgumentException;
use RuntimeException;

class Delete implements SqlStatement
{
    protected ?string $where = null;

    protected bool $all = false;

    public function __construct(
        protected string $table
    ) {
        if (trim($table) === '') {
            throw new InvalidArgumentException('Table name cannot be empty.');
        }
    }

    public function where(string $condition): static
    {
        $condition = trim($condition);

        if ($condition === '') {
            throw new InvalidArgumentException('Delete condition cannot be empty.');
        }

        $this->where = $condition;

        return $this;
    }

    public function all(): static
    {
        $this->all = true;

        return $this;
    }

    public function toSql(?string $driver = null): string
    {
        if ($this->where === null && ! $this->all) {
            throw new RuntimeException(
                "Cannot delete from {$this->table} without WHERE. Use ->all() if intended."
            );
        }

        if ($this->where !== null) {
            return "DELETE FROM {$this->table}\nWHERE {$this->where};";
        }

        return "DELETE FROM {$this->table};";
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}