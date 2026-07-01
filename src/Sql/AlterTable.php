<?php

namespace Eril\Migraw\Sql;

use Eril\Migraw\Sql\SqlStatement;
use InvalidArgumentException;
use RuntimeException;

class AlterTable implements SqlStatement
{
    /**
     * @var array<int,array{type:string,definition:string}>
     */
    protected array $operations = [];

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
     * Examples:
     *
     * ```php
     * ->column('phone VARCHAR(30) NULL')
     * ->column('price DECIMAL(10,2) DEFAULT 0')
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

        return $this->addOperation('add_column', $definition);
    }

    /**
     * Add a column.
     *
     * Examples:
     *
     * ```php
     * ->column('phone VARCHAR(30) NULL')
     * ->column('price DECIMAL(10,2) DEFAULT 0')
     * ```
     *
     * @param string $definition Full SQL column definition.
     *
     * @return static
     */
    public function add(string $definition)
    {
        return $this->column($definition);
    }

    /**
     * Modify an existing column.
     *
     * Examples:
     *
     * ```php
     * ->modify('phone VARCHAR(50) NOT NULL')
     * ->modify('price DECIMAL(12,2)')
     * ```
     *
     * @param string $definition Full SQL column definition.
     *
     * @return static
     */
    public function modify(string $definition): static
    {
        $definition = trim($definition);

        if ($definition === '') {
            throw new InvalidArgumentException('Column definition cannot be empty.');
        }

        return $this->addOperation('modify_column', $definition);
    }

    /**
     * Rename a column.
     *
     * @param string $from Current column name.
     * @param string $to New column name.
     *
     * @return static
     */
    public function renameColumn(string $from, string $to): static
    {
        $from = trim($from);
        $to = trim($to);

        if ($from === '' || $to === '') {
            throw new InvalidArgumentException('Column names cannot be empty.');
        }

        return $this->addOperation('rename_column', "{$from}|{$to}");
    }

    /**
     * Drop a column.
     *
     * @param string $name Column name.
     *
     * @return static
     */
    public function dropColumn(string $name): static
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Column name cannot be empty.');
        }

        return $this->addOperation('drop_column', $name);
    }

    /**
     * Add an index.
     *
     * @param string $name Index name.
     * @param string|array<int,string> $columns Indexed column or columns.
     *
     * @return static
     */
    public function index(string $name, string|array $columns): static
    {
        return $this->addOperation(
            'raw',
            "ADD INDEX {$name} ({$this->columnsList($columns)})"
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
        return $this->addOperation(
            'raw',
            "ADD CONSTRAINT {$name} UNIQUE ({$this->columnsList($columns)})"
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

        $sql = 'ADD '
            . ($name ? "CONSTRAINT {$name} " : '')
            . "FOREIGN KEY ({$local}) REFERENCES {$referencesTable} ({$foreign})";

        if ($onDelete !== '') {
            $sql .= " ON DELETE {$onDelete}";
        }

        if ($onUpdate !== '') {
            $sql .= " ON UPDATE {$onUpdate}";
        }

        return $this->addOperation('raw', $sql);
    }

    /**
     * Drop an index.
     *
     * @param string $name Index name.
     *
     * @return static
     */
    public function dropIndex(string $name): static
    {
        return $this->addOperation('drop_index', $name);
    }

    /**
     * Drop a FOREIGN KEY constraint.
     *
     * @param string $name Constraint name.
     *
     * @return static
     */
    public function dropForeign(string $name): static
    {
        return $this->addOperation('drop_foreign', $name);
    }

    /**
     * Add a raw ALTER TABLE operation.
     *
     * This method can be used for any database-specific operation not
     * covered by the fluent API.
     *
     * Examples:
     *
     * ```php
     * ->raw('RENAME COLUMN old_name TO new_name')
     * ->raw('ADD CHECK (price >= 0)')
     * ```
     *
     * @param string $operation Raw SQL operation.
     *
     * @return static
     */
    public function raw(string $operation): static
    {
        $operation = trim($operation);

        if ($operation === '') {
            throw new InvalidArgumentException('Raw operation cannot be empty.');
        }

        return $this->addOperation('raw', $operation);
    }

    /**
     * Compile the ALTER TABLE statement.
     *
     * @param string|null $driver PDO driver name.
     *
     * @return string
     */
    public function toSql(?string $driver = null): string
    {
        $operations = $this->compileOperations($driver);

        if ($operations === []) {
            throw new RuntimeException("Cannot alter table {$this->table} without operations.");
        }

        return "ALTER TABLE {$this->table}\n    "
            . implode(",\n    ", $operations)
            . ';';
    }

    protected function addOperation(string $type, string $definition): static
    {
        $this->operations[] = [
            'type' => $type,
            'definition' => $definition,
        ];

        return $this;
    }

    protected function compileOperations(?string $driver): array
    {
        $compiled = [];

        foreach ($this->operations as $operation) {
            $compiled[] = $this->compileOperation($operation, $driver);
        }

        return $compiled;
    }

    protected function compileOperation(array $operation, ?string $driver): string
    {
        return match ($operation['type']) {
            'add_column' => "ADD COLUMN {$operation['definition']}",

            'modify_column' => match ($this->driver($driver)) {
                'pgsql' => "ALTER COLUMN {$operation['definition']}",
                default => "MODIFY COLUMN {$operation['definition']}",
            },

            'rename_column' => $this->compileRenameColumn($operation['definition']),

            'drop_column' => "DROP COLUMN {$operation['definition']}",

            'drop_index' => match ($this->driver($driver)) {
                'pgsql',
                'sqlite' => "DROP INDEX {$operation['definition']}",
                default => "DROP INDEX {$operation['definition']}",
            },

            'drop_foreign' => match ($this->driver($driver)) {
                'pgsql' => "DROP CONSTRAINT {$operation['definition']}",
                default => "DROP FOREIGN KEY {$operation['definition']}",
            },

            'raw' => $operation['definition'],

            default => throw new RuntimeException(
                "Unknown alter table operation type: {$operation['type']}"
            ),
        };
    }

    protected function compileRenameColumn(string $definition): string
    {
        [$from, $to] = explode('|', $definition, 2);

        return "RENAME COLUMN {$from} TO {$to}";
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
