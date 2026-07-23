<?php

namespace Eril\Migraw\Sql;

use BackedEnum;
use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Represents an idempotent data population statement.
 *
 * Populate inserts a collection of rows and defines how existing rows should
 * be handled when a unique-key conflict occurs.
 *
 * When no update columns are provided, conflicting rows are preserved.
 * When update columns are provided, those columns are updated using the
 * incoming values.
 *
 * Conflict detection depends on a UNIQUE or PRIMARY KEY constraint existing
 * in the database.
 */
final class Populate implements SqlStatement
{
    /**
     * @var array<int,array<string,mixed>>
     */
    private array $rows;

    /**
     * @var array<int,string>
     */
    private array $columns;

    /**
     * @var array<int,string>
     */
    private array $uniqueBy;

    /**
     * @var array<int,string>
     */
    private array $updateColumns;

    /**
     * @param string                         $table Table name.
     * @param array<int,array<string,mixed>> $rows Rows to insert.
     * @param string|array<int,string>       $uniqueBy Columns identifying a conflict.
     * @param array<int,string>              $updateColumns Columns updated on conflict.
     */
    public function __construct(
        private readonly string $table,
        array $rows,
        string|array $uniqueBy,
        array $updateColumns = []
    ) {
        $this->validateTable($table);

        $this->rows = $this->normalizeRows($rows);
        $this->columns = array_keys($this->rows[0]);
        $this->uniqueBy = $this->normalizeColumns(
            $uniqueBy,
            'Unique columns'
        );
        $this->updateColumns = $this->normalizeColumns(
            $updateColumns,
            'Update columns',
            allowEmpty: true
        );

        $this->validateColumns();
    }



    /**
     * Define the columns updated when an existing row is found.
     *
     * Calling this method replaces the update columns previously supplied through
     * the constructor or through an earlier update() call.
     *
     * Examples:
     *
     * ```
     * Sql::populate('roles', $rows, 'slug')
     *     ->update('name');
     * ```
     *
     * ```
     * Sql::populate('roles', $rows, 'slug')
     *     ->update(['name', 'description']);
     * ```
     *
     * @param string|array<int,string> $columns Columns updated on conflict.
     *
     * @return static
     */
    public function update(string|array $columns): static
    {
        $this->updateColumns = $this->normalizeColumns(
            $columns,
            'Update columns'
        );

        $this->validateColumns();

        return $this;
    }



    /**
     * Convert the population statement to SQL.
     *
     * Supported PDO drivers:
     *
     * - mysql
     * - pgsql
     * - sqlite
     *
     * @param string|null $driver PDO driver name.
     *
     * @return string
     */
    public function toSql(?string $driver = null): string
    {
        $driver = strtolower(trim((string) $driver));

        return match ($driver) {
            'mysql' => $this->compileMySql(),
            'pgsql' => $this->compilePostgreSql(),
            'sqlite' => $this->compileSqlite(),
            default => throw new RuntimeException(
                $driver === ''
                    ? 'Populate requires a database driver.'
                    : "Populate is not supported for driver: {$driver}"
            ),
        };
    }

    /**
     * Convert the statement using no implicit database driver.
     *
     * Populate is driver-specific and therefore cannot safely produce SQL
     * without receiving a driver.
     *
     * @return string
     */
    public function __toString(): string
    {
        throw new RuntimeException(
            'Populate cannot be converted to SQL without a database driver.'
        );
    }

    /**
     * Compile the statement for MySQL or MariaDB.
     *
     * MySQL determines conflicts from PRIMARY KEY and UNIQUE constraints.
     * The uniqueBy columns are validated by Migraw but are not written into
     * MySQL's ON DUPLICATE KEY syntax.
     *
     * @return string
     */
    private function compileMySql(): string
    {
        $insert = $this->compileInsert('mysql');

        if ($this->updateColumns !== []) {
            $updates = array_map(
                fn(string $column): string =>
                $this->quoteIdentifier($column, 'mysql')
                    . ' = VALUES('
                    . $this->quoteIdentifier($column, 'mysql')
                    . ')',
                $this->updateColumns
            );

            return $insert
                . PHP_EOL
                . 'ON DUPLICATE KEY UPDATE '
                . implode(', ', $updates);
        }

        /*
         * MySQL does not support ON DUPLICATE KEY DO NOTHING.
         *
         * A harmless self-assignment avoids INSERT IGNORE, which could hide
         * errors unrelated to unique-key conflicts.
         */
        $column = $this->uniqueBy[0];
        $quoted = $this->quoteIdentifier($column, 'mysql');

        return $insert
            . PHP_EOL
            . "ON DUPLICATE KEY UPDATE {$quoted} = {$quoted}";
    }

    /**
     * Compile the statement for PostgreSQL.
     *
     * @return string
     */
    private function compilePostgreSql(): string
    {
        return $this->compileOnConflict('pgsql');
    }

    /**
     * Compile the statement for SQLite.
     *
     * @return string
     */
    private function compileSqlite(): string
    {
        return $this->compileOnConflict('sqlite');
    }

    /**
     * Compile PostgreSQL or SQLite ON CONFLICT syntax.
     *
     * @param string $driver PDO driver name.
     *
     * @return string
     */
    private function compileOnConflict(string $driver): string
    {
        $insert = $this->compileInsert($driver);

        $conflictColumns = implode(
            ', ',
            array_map(
                fn(string $column): string =>
                $this->quoteIdentifier($column, $driver),
                $this->uniqueBy
            )
        );

        if ($this->updateColumns === []) {
            return $insert
                . PHP_EOL
                . "ON CONFLICT ({$conflictColumns}) DO NOTHING";
        }

        $updates = array_map(
            fn(string $column): string =>
            $this->quoteIdentifier($column, $driver)
                . ' = EXCLUDED.'
                . $this->quoteIdentifier($column, $driver),
            $this->updateColumns
        );

        return $insert
            . PHP_EOL
            . "ON CONFLICT ({$conflictColumns}) DO UPDATE SET "
            . implode(', ', $updates);
    }

    /**
     * Compile the INSERT portion of the statement.
     *
     * @param string $driver PDO driver name.
     *
     * @return string
     */
    private function compileInsert(string $driver): string
    {
        $table = $this->quoteQualifiedIdentifier(
            $this->table,
            $driver
        );

        $columns = implode(
            ', ',
            array_map(
                fn(string $column): string =>
                $this->quoteIdentifier($column, $driver),
                $this->columns
            )
        );

        $values = [];

        foreach ($this->rows as $row) {
            $rowValues = [];

            foreach ($this->columns as $column) {
                $rowValues[] = $this->compileValue(
                    $row[$column],
                    $driver
                );
            }

            $values[] = '(' . implode(', ', $rowValues) . ')';
        }

        return "INSERT INTO {$table} ({$columns})"
            . PHP_EOL
            . 'VALUES'
            . PHP_EOL
            . '    '
            . implode(',' . PHP_EOL . '    ', $values);
    }

    /**
     * Convert a PHP value into an SQL literal.
     *
     * This class generates complete SQL strings because the current
     * SqlStatement contract does not expose prepared-statement bindings.
     *
     * @param mixed  $value Value to compile.
     * @param string $driver PDO driver name.
     *
     * @return string
     */
    private function compileValue(mixed $value, string $driver): string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return match ($driver) {
                'pgsql' => $value ? 'TRUE' : 'FALSE',
                default => $value ? '1' : '0',
            };
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new InvalidArgumentException(
                    'Populate does not support infinite or NaN float values.'
                );
            }

            return str_replace(
                ',',
                '.',
                sprintf('%.14G', $value)
            );
        }

        if (is_string($value)) {
            return "'" . str_replace("'", "''", $value) . "'";
        }

        throw new InvalidArgumentException(
            sprintf(
                'Unsupported Populate value type: %s.',
                get_debug_type($value)
            )
        );
    }

    /**
     * Validate the target table name.
     *
     * Qualified names such as public.roles are allowed.
     *
     * @param string $table Table name.
     *
     * @return void
     */
    private function validateTable(string $table): void
    {
        if (trim($table) === '') {
            throw new InvalidArgumentException(
                'Populate table name cannot be empty.'
            );
        }

        foreach (explode('.', $table) as $part) {
            $this->validateIdentifier($part, 'Table name');
        }
    }

    /**
     * Normalize and validate inserted rows.
     *
     * Every row must contain exactly the same columns in the same order.
     *
     * @param array<int,mixed> $rows Rows to normalize.
     *
     * @return array<int,array<string,mixed>>
     */
    private function normalizeRows(array $rows): array
    {
        if ($rows === []) {
            throw new InvalidArgumentException(
                'Populate requires at least one row.'
            );
        }

        $normalized = [];
        $expectedColumns = null;

        foreach ($rows as $index => $row) {
           
            foreach ($row as $column => $_) {
                if (! is_string($column)) {
                    throw new InvalidArgumentException(
                        "Populate row {$index} contains an invalid column name."
                    );
                }

                $this->validateIdentifier($column, 'Column name');
            }

            $columns = array_keys($row);

            if ($expectedColumns === null) {
                $expectedColumns = $columns;
            } elseif ($columns !== $expectedColumns) {
                throw new InvalidArgumentException(
                    "Populate row {$index} must contain the same columns "
                        . 'in the same order as the first row.'
                );
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    /**
     * Normalize a column argument.
     *
     * @param string|array<int,mixed> $columns Columns.
     * @param string                   $label Validation label.
     * @param bool                     $allowEmpty Whether an empty list is allowed.
     *
     * @return array<int,string>
     */
    private function normalizeColumns(
        string|array $columns,
        string $label,
        bool $allowEmpty = false
    ): array {
        $columns = is_string($columns)
            ? [$columns]
            : array_values($columns);

        if ($columns === []) {
            if ($allowEmpty) {
                return [];
            }

            throw new InvalidArgumentException(
                "{$label} cannot be empty."
            );
        }

        $normalized = [];

        foreach ($columns as $column) {
            if (! is_string($column)) {
                throw new InvalidArgumentException(
                    "{$label} must contain only strings."
                );
            }

            $column = trim($column);

            $this->validateIdentifier($column, $label);

            if (! in_array($column, $normalized, true)) {
                $normalized[] = $column;
            }
        }

        return $normalized;
    }

    /**
     * Ensure conflict and update columns exist in the inserted rows.
     *
     * @return void
     */
    private function validateColumns(): void
    {
        foreach ($this->uniqueBy as $column) {
            if (! in_array($column, $this->columns, true)) {
                throw new InvalidArgumentException(
                    "Unique column '{$column}' is not present in the populated rows."
                );
            }
        }

        foreach ($this->updateColumns as $column) {
            if (! in_array($column, $this->columns, true)) {
                throw new InvalidArgumentException(
                    "Update column '{$column}' is not present in the populated rows."
                );
            }
        }
    }

    /**
     * Validate an SQL identifier.
     *
     * @param string $identifier Identifier.
     * @param string $label Validation label.
     *
     * @return void
     */
    private function validateIdentifier(
        string $identifier,
        string $label
    ): void {
        if (
            $identifier === ''
            || preg_match(
                '/^[A-Za-z_][A-Za-z0-9_$]*$/',
                $identifier
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                "{$label} contains an invalid SQL identifier: {$identifier}"
            );
        }
    }

    /**
     * Quote a database identifier.
     *
     * @param string $identifier Identifier.
     * @param string $driver PDO driver name.
     *
     * @return string
     */
    private function quoteIdentifier(
        string $identifier,
        string $driver
    ): string {
        return $driver === 'mysql'
            ? "`{$identifier}`"
            : "\"{$identifier}\"";
    }

    /**
     * Quote a possibly qualified identifier.
     *
     * Example:
     *
     * public.roles becomes "public"."roles" on PostgreSQL.
     *
     * @param string $identifier Identifier.
     * @param string $driver PDO driver name.
     *
     * @return string
     */
    private function quoteQualifiedIdentifier(
        string $identifier,
        string $driver
    ): string {
        return implode(
            '.',
            array_map(
                fn(string $part): string =>
                $this->quoteIdentifier($part, $driver),
                explode('.', $identifier)
            )
        );
    }
}
