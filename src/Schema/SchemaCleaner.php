<?php

namespace Eril\Migraw\Schema;

use PDO;
use RuntimeException;

final class SchemaCleaner
{
    private string $driver;

    public function __construct(
        private readonly PDO $pdo
    ) {
        $this->driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Drop all user-defined tables.
     *
     * @return string[] Dropped table names.
     */
    public function dropAllTables(): array
    {
        $tables = $this->tables();

        if ($tables === []) {
            return [];
        }

        $this->disableForeignKeyChecks();

        try {
            foreach ($tables as $table) {
                $this->pdo->exec(
                    $this->dropTableSql($table)
                );
            }
        } finally {
            $this->enableForeignKeyChecks();
        }

        return $tables;
    }

    /**
     * Return all user-defined tables.
     *
     * @return string[]
     */
    private function tables(): array
    {
        $sql = match ($this->driver) {
            'mysql' => "SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'",

            'pgsql' => <<<'SQL'
                SELECT tablename
                FROM pg_tables
                WHERE schemaname = current_schema()
                ORDER BY tablename
                SQL,

            'sqlite' => <<<'SQL'
                SELECT name
                FROM sqlite_master
                WHERE type = 'table'
                  AND name NOT LIKE 'sqlite_%'
                ORDER BY name
                SQL,

            default => throw new RuntimeException(
                "Fresh is not supported for driver: {$this->driver}"
            ),
        };

        $statement = $this->pdo->query($sql);

        if ($statement === false) {
            throw new RuntimeException(
                'Unable to retrieve database tables.'
            );
        }

        return array_map(
            'strval',
            $statement->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    private function dropTableSql(string $table): string
    {
        $table = $this->quote($table);

        return match ($this->driver) {
            'pgsql' => "DROP TABLE IF EXISTS {$table} CASCADE",
            default => "DROP TABLE IF EXISTS {$table}",
        };
    }

    private function quote(string $identifier): string
    {
        return match ($this->driver) {
            'mysql' => '`'
                . str_replace('`', '``', $identifier)
                . '`',

            'pgsql', 'sqlite' => '"'
                . str_replace('"', '""', $identifier)
                . '"',

            default => throw new RuntimeException(
                "Unsupported database driver: {$this->driver}"
            ),
        };
    }

    private function disableForeignKeyChecks(): void
    {
        $sql = match ($this->driver) {
            'mysql' => 'SET FOREIGN_KEY_CHECKS = 0',
            'sqlite' => 'PRAGMA foreign_keys = OFF',
            default => null,
        };

        if ($sql !== null) {
            $this->pdo->exec($sql);
        }
    }

    private function enableForeignKeyChecks(): void
    {
        $sql = match ($this->driver) {
            'mysql' => 'SET FOREIGN_KEY_CHECKS = 1',
            'sqlite' => 'PRAGMA foreign_keys = ON',
            default => null,
        };

        if ($sql !== null) {
            $this->pdo->exec($sql);
        }
    }
}