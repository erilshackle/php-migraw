<?php

namespace Eril\Migraw\Schema;

use Eril\Migraw\Sql\SqlStatement;
use InvalidArgumentException;
use RuntimeException;

class CreateTable implements SqlStatement
{
    protected bool $ifNotExists = false;

    /**
     * @var array<int,array{type:string,definition:string}>
     */
    protected array $definitions = [];

    public function __construct(
        protected string $table
    ) {
        if (trim($table) === '') {
            throw new InvalidArgumentException('Table name cannot be empty.');
        }
    }

    /**
     * Add IF NOT EXISTS to the CREATE TABLE statement.
     *
     * @return static
     */
    public function ifNotExists(): static
    {
        $this->ifNotExists = true;

        return $this;
    }

    /**
     * Add a conventional primary key column.
     *
     * When a shorthand is provided, Migraw generates a driver-specific
     * primary key definition.
     *
     * Examples:
     *
     * ```php
     * ->id()
     * ->id('uuid')
     * ->id('user_id')
     * ->id('uuid CHAR(36) PRIMARY KEY')
     * ```
     *
     * @param string $definition Column definition or shorthand.
     *
     * @return static
     */
    public function id(string $definition = 'id'): static
    {
        $definition = trim($definition);

        if ($definition === '') {
            throw new InvalidArgumentException('ID definition cannot be empty.');
        }

        return $this->addDefinition('id', $definition);
    }

    /**
     * Add a column definition.
     *
     * Examples:
     *
     * ```php
     * ->column('name VARCHAR(255) NOT NULL')
     * ->column('price DECIMAL(10,2) DEFAULT 0')
     * ->column('created_at TIMESTAMP NULL')
     * ```
     *
     * @param string $definition Full SQL column definition.
     *
     * @return static
     */
    public function column(string $definition): static
    {
        $definition = trim($definition);

        if ($definition === '') {
            throw new InvalidArgumentException('Column definition cannot be empty.');
        }

        return $this->addDefinition('column', $definition);
    }

    /**
     * Add conventional timestamp columns.
     *
     * The generated SQL depends on the target database driver.
     *
     * Adds:
     *
     * - created_at
     * - updated_at
     *
     * @return static
     */
    public function timestamps(): static
    {
        return $this->addDefinition('timestamps', '');
    }

    /**
     * Add a nullable soft delete timestamp column.
     *
     * The generated SQL depends on the target database driver.
     *
     * @param string $name Soft delete column name.
     *
     * @return static
     */
    public function softDeletes(string $name = 'deleted_at'): static
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Soft delete column name cannot be empty.');
        }

        return $this->addDefinition('soft_deletes', $name);
    }

    /**
     * Add a PRIMARY KEY constraint.
     *
     * @param string|array<int,string> $columns Column name or list of columns.
     * @param string|null $name Optional constraint name.
     *
     * @return static
     */
    public function primary(string|array $columns, ?string $name = null): static
    {
        $columns = $this->columnsList($columns);

        return $this->constraint(
            $name
                ? "CONSTRAINT {$name} PRIMARY KEY ({$columns})"
                : "PRIMARY KEY ({$columns})"
        );
    }

    /**
     * Add a UNIQUE constraint.
     *
     * @param string $name Constraint name.
     * @param string|array<int,string> $columns Column name or list of columns.
     *
     * @return static
     */
    public function unique(string $name, string|array $columns): static
    {
        return $this->constraint(
            "CONSTRAINT {$name} UNIQUE ({$this->columnsList($columns)})"
        );
    }

    /**
     * Add an index definition.
     *
     * @param string $name Index name.
     * @param string|array<int,string> $columns Indexed column or columns.
     *
     * @return static
     */
    public function index(string $name, string|array $columns): static
    {
        return $this->constraint(
            "INDEX {$name} ({$this->columnsList($columns)})"
        );
    }

    /**
     * Add a FOREIGN KEY constraint.
     *
     * @param string|array<int,string> $columns Local column or columns.
     * @param string $referencesTable Referenced table.
     * @param string|array<int,string> $referencesColumns Referenced column or columns.
     * @param string|null $name Optional constraint name.
     * @param string $onDelete Optional ON DELETE action.
     * @param string $onUpdate Optional ON UPDATE action.
     *
     * @return static
     */
    public function foreign(
        string|array $columns,
        string $referencesTable,
        string|array $referencesColumns = 'id',
        ?string $name = null,
        string $onDelete = '',
        string $onUpdate = ''
    ): static {
        $local = $this->columnsList($columns);
        $foreign = $this->columnsList($referencesColumns);

        $sql = ($name ? "CONSTRAINT {$name} " : '')
            . "FOREIGN KEY ({$local}) REFERENCES {$referencesTable} ({$foreign})";

        if ($onDelete !== '') {
            $sql .= " ON DELETE {$onDelete}";
        }

        if ($onUpdate !== '') {
            $sql .= " ON UPDATE {$onUpdate}";
        }

        return $this->constraint($sql);
    }

    /**
     * Add a table-level constraint.
     *
     * Examples:
     *
     * ```php
     * ->constraint('PRIMARY KEY (id)')
     * ->constraint('CHECK (price >= 0)')
     * ->constraint('UNIQUE (email)')
     * ```
     *
     * @param string $definition SQL constraint definition.
     *
     * @return static
     */
    public function constraint(string $definition): static
    {
        $definition = trim($definition);

        if ($definition === '') {
            throw new InvalidArgumentException('Constraint definition cannot be empty.');
        }

        return $this->addDefinition('constraint', $definition);
    }

    /**
     * Add a raw table definition.
     *
     * This method can be used for any database-specific definition not
     * covered by the fluent API.
     *
     * Examples:
     *
     * ```php
     * ->raw('CHECK (price >= 0)')
     * ->raw('FULLTEXT INDEX ft_name (name)')
     * ```
     *
     * @param string $definition Raw SQL definition.
     *
     * @return static
     */
    public function raw(string $definition): static
    {
        $definition = trim($definition);

        if ($definition === '') {
            throw new InvalidArgumentException('Raw definition cannot be empty.');
        }

        return $this->addDefinition('raw', $definition);
    }

    /**
     * Compile the CREATE TABLE statement.
     *
     * @param string|null $driver PDO driver name.
     *
     * @return string
     */
    public function toSql(?string $driver = null): string
    {
        $parts = $this->compileDefinitions($driver);

        if ($parts === []) {
            throw new RuntimeException("Cannot create table {$this->table} without definitions.");
        }

        $ifNotExists = $this->ifNotExists ? 'IF NOT EXISTS ' : '';

        return "CREATE TABLE {$ifNotExists}{$this->table} (\n    "
            . implode(",\n    ", $parts)
            . "\n);";
    }

    protected function addDefinition(string $type, string $definition): static
    {
        $this->definitions[] = [
            'type' => $type,
            'definition' => $definition,
        ];

        return $this;
    }

    protected function compileDefinitions(?string $driver): array
    {
        $compiled = [];

        foreach ($this->definitions as $definition) {
            $compiled = array_merge(
                $compiled,
                $this->compileDefinition($definition, $driver)
            );
        }

        return $compiled;
    }

    protected function compileDefinition(array $definition, ?string $driver): array
    {
        return match ($definition['type']) {
            'id' => [
                $this->compileId($definition['definition'], $driver),
            ],

            'timestamps' => [
                $this->createdAtColumn($driver),
                $this->updatedAtColumn($driver),
            ],

            'soft_deletes' => [
                $this->softDeleteColumn($definition['definition'], $driver),
            ],

            'column',
            'constraint',
            'raw' => [
                $definition['definition'],
            ],

            default => throw new RuntimeException(
                "Unknown create table definition type: {$definition['type']}"
            ),
        };
    }

    protected function compileId(string $definition, ?string $driver): string
    {
        if (str_contains($definition, ' ')) {
            return $definition;
        }

        if ($definition === 'uuid') {
            return 'uuid CHAR(36) PRIMARY KEY';
        }

        return match ($this->driver($driver)) {
            'sqlite' => "{$definition} INTEGER PRIMARY KEY AUTOINCREMENT",
            'pgsql' => "{$definition} SERIAL PRIMARY KEY",
            default => "{$definition} INT AUTO_INCREMENT PRIMARY KEY",
        };
    }

    protected function createdAtColumn(?string $driver): string
    {
        return match ($this->driver($driver)) {
            'sqlite' => 'created_at TEXT DEFAULT CURRENT_TIMESTAMP',
            'pgsql' => 'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            default => 'created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
        };
    }

    protected function updatedAtColumn(?string $driver): string
    {
        return match ($this->driver($driver)) {
            'sqlite' => 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP',
            'pgsql' => 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            default => 'updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        };
    }

    protected function softDeleteColumn(string $name, ?string $driver): string
    {
        return match ($this->driver($driver)) {
            'sqlite' => "{$name} TEXT NULL",
            default => "{$name} TIMESTAMP NULL",
        };
    }

    protected function columnsList(string|array $columns): string
    {
        return is_array($columns)
            ? implode(', ', $columns)
            : $columns;
    }

    protected function driver(?string $driver): string
    {
        return strtolower($driver ?: 'mysql');
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
