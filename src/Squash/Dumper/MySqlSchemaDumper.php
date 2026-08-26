<?php

namespace Eril\Migraw\Squash\Dumper;

use PDO;

final class MySqlSchemaDumper implements SchemaDriverDumper
{
    public function __construct(
        protected PDO $pdo,
        protected string $migrationTable = 'migrations'
    ) {}

    public function dump(): array
    {
        $stmt = $this->pdo->query(<<<'SQL'
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME
SQL);

        $schema = [];

        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if ($table === $this->migrationTable) {
                continue;
            }

            $row = $this->pdo
                ->query('SHOW CREATE TABLE ' . $this->quote($table))
                ->fetch(PDO::FETCH_ASSOC);

            $create = array_values($row)[1] ?? null;

            if (!$create) {
                continue;
            }

            $create = preg_replace(
                '/\sAUTO_INCREMENT=\d+/i',
                '',
                $create
            );

            $schema[$table] = [
                rtrim($create, ';') . ';',
            ];
        }

        return $schema;
    }

    public function beforeCreate(): array
    {
        return ['SET FOREIGN_KEY_CHECKS = 0;'];
    }

    public function afterCreate(): array
    {
        return ['SET FOREIGN_KEY_CHECKS = 1;'];
    }

    public function beforeDrop(): array
    {
        return ['SET FOREIGN_KEY_CHECKS = 0;'];
    }

    public function afterDrop(): array
    {
        return ['SET FOREIGN_KEY_CHECKS = 1;'];
    }

    public function dropTable(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . $this->quote($table) . ';';
    }

    protected function quote(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}