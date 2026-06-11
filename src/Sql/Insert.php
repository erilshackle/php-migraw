<?php

namespace Eril\Migraw\Sql;

use InvalidArgumentException;
use RuntimeException;

class Insert implements SqlStatement
{
    protected bool $ignore = false;

    protected array $rows = [];

    protected array $duplicateColumns = [];

    protected array $duplicateExpressions = [];

    public function __construct(
        protected string $table
    ) {
        if (trim($table) === '') {
            throw new InvalidArgumentException('Table name cannot be empty.');
        }
    }

    public function ignore(): static
    {
        $this->ignore = true;

        return $this;
    }

    public function row(array $values): static
    {
        if ($values === []) {
            throw new InvalidArgumentException('Insert row cannot be empty.');
        }

        $this->rows[] = $values;

        return $this;
    }

    public function rows(array $rows): static
    {
        if ($rows === []) {
            throw new InvalidArgumentException('Insert rows cannot be empty.');
        }

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new InvalidArgumentException('Each insert row must be an array.');
            }

            $this->row($row);
        }

        return $this;
    }

    public function onDuplicateUpdate(array $columns): static
    {
        if ($columns === []) {
            throw new InvalidArgumentException('Duplicate update columns cannot be empty.');
        }

        foreach ($columns as $column) {
            $this->duplicateColumns[] = trim((string) $column);
        }

        return $this;
    }

    public function onDuplicate(string $expression): static
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new InvalidArgumentException('Duplicate update expression cannot be empty.');
        }

        $this->duplicateExpressions[] = $expression;

        return $this;
    }

    public function toSql(): string
    {
        if ($this->rows === []) {
            throw new RuntimeException("Cannot insert into {$this->table} without rows.");
        }

        $columns = array_keys($this->rows[0]);

        $this->assertRowsHaveSameColumns($columns);

        $insert = $this->ignore ? 'INSERT IGNORE INTO' : 'INSERT INTO';

        $sql = "{$insert} {$this->table} ("
            . implode(', ', $columns)
            . ")\nVALUES\n    "
            . implode(",\n    ", $this->compileRows($columns));

        $duplicate = $this->compileDuplicateUpdate();

        if ($duplicate !== '') {
            $sql .= "\n{$duplicate}";
        }

        return $sql . ';';
    }

    protected function assertRowsHaveSameColumns(array $columns): void
    {
        foreach ($this->rows as $row) {
            $rowColumns = array_keys($row);

            if ($rowColumns !== $columns) {
                throw new RuntimeException('All insert rows must have the same columns and order.');
            }
        }
    }

    protected function compileRows(array $columns): array
    {
        $compiled = [];

        foreach ($this->rows as $row) {
            $values = [];

            foreach ($columns as $column) {
                $values[] = $this->quote($row[$column]);
            }

            $compiled[] = '(' . implode(', ', $values) . ')';
        }

        return $compiled;
    }

    protected function compileDuplicateUpdate(): string
    {
        $parts = [];

        foreach ($this->duplicateColumns as $column) {
            if ($column === '') {
                continue;
            }

            $parts[] = "{$column} = VALUES({$column})";
        }

        foreach ($this->duplicateExpressions as $expression) {
            $parts[] = $expression;
        }

        if ($parts === []) {
            return '';
        }

        return "ON DUPLICATE KEY UPDATE\n    " . implode(",\n    ", $parts);
    }

    protected function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return "'" . str_replace("'", "''", (string) $value) . "'";
    }

    public function __toString(): string
    {
        return $this->toSql();
    }
}