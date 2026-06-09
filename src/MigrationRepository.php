<?php

namespace Eril\SqlMigrator;

use PDO;

class MigrationRepository
{
    public function __construct(
        protected PDO $pdo,
        protected string $table = 'migrations'
    ) {}


    public function ensureTableExists(): void
    {
        $this->pdo->exec($this->createMigrationsTableSql());
    }

    protected function createMigrationsTableSql(): string
    {
        return match ($this->driver()) {
            'sqlite' => "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                checksum VARCHAR(64) NOT NULL,
                executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ",

            'pgsql' => "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ",

            default => "
            CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ",
        };
    }


    protected function driver(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function getRan(): array
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->query("
            SELECT migration 
            FROM {$this->table}
            ORDER BY batch ASC, migration ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getLastBatch(): array
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->query("
            SELECT migration 
            FROM {$this->table}
            WHERE batch = (
                SELECT MAX(batch) FROM {$this->table}
            )
            ORDER BY migration DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function getNextBatchNumber(): int
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->query("SELECT MAX(batch) FROM {$this->table}");

        return ((int) $stmt->fetchColumn()) + 1;
    }

    public function checksumOf(
        string $migration
    ): ?string {
        $stmt = $this->pdo->prepare("
        SELECT checksum
        FROM {$this->table}
        WHERE migration = :migration
        LIMIT 1
    ");

        $stmt->execute([
            'migration' => $migration,
        ]);

        return $stmt->fetchColumn() ?: null;
    }

    public function log(string $migration, int $batch, string $checksum): void
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->prepare("
        INSERT INTO {$this->table} (migration, batch, checksum)
        VALUES (:migration, :batch, :checksum)
    ");

        $stmt->execute([
            'migration' => $migration,
            'batch' => $batch,
            'checksum' => $checksum
        ]);
    }

    public function delete(string $migration): void
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->table}
            WHERE migration = :migration
        ");

        $stmt->execute([
            'migration' => $migration,
        ]);
    }

    public function all(): array
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->query("
            SELECT migration, batch, executed_at
            FROM {$this->table}
            ORDER BY batch ASC, migration ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
