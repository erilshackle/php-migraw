<?php

namespace Eril\Migraw\Sql;

use InvalidArgumentException;
use RuntimeException;

/**
 * Low-level CREATE TABLE SQL helper.
 *
 * This class is kept as a compatibility layer for the older Sql:: API. New
 * migrations should usually prefer the helper methods available on Migration.
 */
class CreateTable implements SqlStatement
{
    /** @var string[] */
    protected array $fields = [];

    /** @var string[] */
    protected array $constraints = [];

    protected bool $ifNotExists = false;

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
     * Add IF NOT EXISTS.
     *
     * @return static
     */
    public function ifNotExists(): static
    {
        $this->ifNotExists = true;

        return $this;
    }

    /**
     * Add a raw column definition.
     *
     * Example: id INT AUTO_INCREMENT PRIMARY KEY
     *
     * @param string $definition Column definition.
     *
     * @return static
     */
    public function field(string $definition): static
    {
        $definition = trim($definition);

        if ($definition === '') {
            throw new InvalidArgumentException('Field definition cannot be empty.');
        }

        $this->fields[] = $definition;

        return $this;
    }

    /**
     * Add a table constraint.
     *
     * @param string $definition Constraint definition.
     *
     * @return static
     */
    public function constraint(string $definition): static
    {
        $definition = trim($definition);

        if ($definition === '') {
            throw new InvalidArgumentException('Constraint definition cannot be empty.');
        }

        $this->constraints[] = $definition;

        return $this;
    }

    /**
     * Convert the CREATE TABLE statement to SQL.
     *
     * @param string|null $driver PDO driver name. Currently unused.
     *
     * @return string
     */
    public function toSql(?string $driver = null): string
    {
        $parts = array_merge($this->fields, $this->constraints);

        if ($parts === []) {
            throw new RuntimeException("Cannot create table {$this->table} without fields or constraints.");
        }

        $body = implode(",\n    ", $parts);
        $ifNotExists = $this->ifNotExists ? 'IF NOT EXISTS ' : '';

        return "CREATE TABLE {$ifNotExists}{$this->table} (\n    {$body}\n);";
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
