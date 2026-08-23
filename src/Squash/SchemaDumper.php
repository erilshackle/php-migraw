<?php

namespace Eril\Migraw\Squash;

use PDO;
use RuntimeException;

/**
 * Dumps the current database schema as raw CREATE TABLE statements.
 *
 * The first implementation intentionally targets MySQL/MariaDB, where
 * SHOW CREATE TABLE provides a faithful representation of the final schema.
 */
final class SchemaDumper
{
    public function __construct(
        protected PDO $pdo,
        protected string $migrationTable = 'migrations'
    ) {}

    /**
     * Dump all application tables except Migraw's migration repository.
     *
     * @return array<string,string> Table name => CREATE TABLE SQL.
     */
    public function dump(): array
    {
        $this->assertSupportedDriver();

        $schema = [];

        foreach ($this->tables() as $table) {
            $stmt = $this->pdo->query(
                'SHOW CREATE TABLE ' . $this->quoteIdentifier($table)
            );

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (! is_array($row)) {
                throw new RuntimeException(
                    "Unable to inspect table: {$table}"
                );
            }

            $createSql = $row['Create Table'] ?? array_values($row)[1] ?? null;

            if (! is_string($createSql) || trim($createSql) === '') {
                throw new RuntimeException(
                    "Unable to read CREATE TABLE statement for: {$table}"
                );
            }

            $schema[$table] = $this->normalizeCreateSql($createSql);
        }

        return $schema;
    }

    /**
     * Return application base tables in deterministic order.
     *
     * @return string[]
     */
    public function tables(): array
    {
        $this->assertSupportedDriver();

        $stmt = $this->pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tables = [];

        foreach ($stmt->fetchAll(PDO::FETCH_NUM) as $row) {
            $table = (string) ($row[0] ?? '');

            if ($table === '' || $table === $this->migrationTable) {
                continue;
            }

            $tables[] = $table;
        }

        sort($tables, SORT_STRING);

        return $tables;
    }

    /**
     * Remove runtime-only table options from a schema baseline.
     */
    protected function normalizeCreateSql(string $sql): string
    {
        $sql = preg_replace('/\sAUTO_INCREMENT=\d+/i', '', $sql) ?? $sql;

        return rtrim(trim($sql), ';') . ';';
    }

    protected function assertSupportedDriver(): void
    {
        $driver = strtolower((string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));

        if ($driver !== 'mysql') {
            throw new RuntimeException(
                "Squash currently supports MySQL/MariaDB only. Current driver: {$driver}"
            );
        }
    }

    protected function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
