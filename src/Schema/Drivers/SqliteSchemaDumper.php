<?php

namespace Eril\Migraw\Schema\Drivers;

use Eril\Migraw\Schema\SchemaDriverDumper;
use PDO;

final class SqliteSchemaDumper implements SchemaDriverDumper
{
    public function __construct(
        protected PDO $pdo,
        protected string $migrationTable = 'migrations'
    ) {}

    public function dump(): array
    {
        $stmt = $this->pdo->query(<<<'SQL'
SELECT name, sql
FROM sqlite_schema
WHERE type = 'table'
  AND name NOT LIKE 'sqlite_%'
  AND sql IS NOT NULL
ORDER BY name
SQL);

        $schema = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $table = $row['name'];

            if ($table === $this->migrationTable) {
                continue;
            }

            $schema[$table] = [
                rtrim($row['sql'], ';') . ';',
            ];

            $indexStmt = $this->pdo->prepare(<<<'SQL'
SELECT sql
FROM sqlite_schema
WHERE type = 'index'
  AND tbl_name = :table
  AND sql IS NOT NULL
ORDER BY name
SQL);

            $indexStmt->execute(['table' => $table]);

            foreach ($indexStmt->fetchAll(PDO::FETCH_COLUMN) as $index) {
                $schema[$table][] = rtrim($index, ';') . ';';
            }
        }

        return $this->sortByForeignKeys($schema);
    }

    public function beforeCreate(): array
    {
        return ['PRAGMA foreign_keys = OFF;'];
    }

    public function afterCreate(): array
    {
        return ['PRAGMA foreign_keys = ON;'];
    }

    public function beforeDrop(): array
    {
        return ['PRAGMA foreign_keys = OFF;'];
    }

    public function afterDrop(): array
    {
        return ['PRAGMA foreign_keys = ON;'];
    }

    public function dropTable(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->quote($table) . ';';
    }

    protected function sortByForeignKeys(array $schema): array
    {
        $sorted = [];
        $visiting = [];

        $visit = function (string $table) use (
            &$visit,
            &$sorted,
            &$visiting,
            $schema
        ): void {
            if (isset($sorted[$table]) || isset($visiting[$table])) {
                return;
            }

            $visiting[$table] = true;

            $stmt = $this->pdo->query(
                'PRAGMA foreign_key_list(' . $this->quote($table) . ')'
            );

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fk) {
                $parent = $fk['table'] ?? null;

                if ($parent && isset($schema[$parent])) {
                    $visit($parent);
                }
            }

            unset($visiting[$table]);

            $sorted[$table] = $schema[$table];
        };

        foreach (array_keys($schema) as $table) {
            $visit($table);
        }

        return $sorted;
    }

    protected function quote(string $name): string
    {
        return '"' . str_replace('"', '""', $name) . '"';
    }
}