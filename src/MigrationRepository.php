<?php

namespace Eril\Migraw;

use PDO;

/**
 * Stores and reads executed migration metadata.
 *
 * The repository uses a small database table to track executed migration names,
 * batches, execution time and a checksum used for diagnostic status reporting.
 */
class MigrationRepository
{
    /**
     * @param PDO $pdo Database connection.
     * @param string $table Migration table name.
     */
    public function __construct(
        protected PDO $pdo,
        protected string $table = 'migrations'
    ) {}

    /**
     * Ensure the migration table exists and has the required columns.
     *
     * @return void
     */
    public function ensureTableExists(): void
    {
        $this->pdo->exec($this->createMigrationsTableSql());
        $this->ensureChecksumColumnExists();
    }

    /**
     * Build the database-specific migration table creation SQL.
     *
     * @return string
     */
    protected function createMigrationsTableSql(): string
    {
        return match ($this->driver()) {
            'sqlite' => "
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration TEXT NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    checksum VARCHAR(64) NULL,
                    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ",

            'pgsql' => "
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INTEGER NOT NULL,
                    checksum VARCHAR(64) NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ",

            default => "
                CREATE TABLE IF NOT EXISTS {$this->table} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL UNIQUE,
                    batch INT NOT NULL,
                    checksum VARCHAR(64) NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ",
        };
    }

    /**
     * Ensure older migration tables gain the diagnostic checksum column.
     *
     * @return void
     */
    protected function ensureChecksumColumnExists(): void
    {
        if ($this->hasColumn('checksum')) {
            return;
        }

        $this->pdo->exec("ALTER TABLE {$this->table} ADD COLUMN checksum VARCHAR(64) NULL");
    }

    /**
     * Determine whether the migration table has a given column.
     *
     * @param string $column Column name.
     *
     * @return bool
     */
    protected function hasColumn(string $column): bool
    {
        return match ($this->driver()) {
            'sqlite' => $this->hasSqliteColumn($column),
            'pgsql' => $this->hasPostgresColumn($column),
            default => $this->hasMysqlColumn($column),
        };
    }

    /**
     * Check for a column in SQLite.
     *
     * @param string $column Column name.
     *
     * @return bool
     */
    protected function hasSqliteColumn(string $column): bool
    {
        $stmt = $this->pdo->query("PRAGMA table_info({$this->table})");

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for a column in PostgreSQL.
     *
     * @param string $column Column name.
     *
     * @return bool
     */
    protected function hasPostgresColumn(string $column): bool
    {
        $stmt = $this->pdo->prepare(" 
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = current_schema()
              AND table_name = :table
              AND column_name = :column
            LIMIT 1
        ");

        $stmt->execute([
            'table' => $this->table,
            'column' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Check for a column in MySQL or MariaDB.
     *
     * @param string $column Column name.
     *
     * @return bool
     */
    protected function hasMysqlColumn(string $column): bool
    {
        $database = $this->pdo->query('SELECT DATABASE()')->fetchColumn();

        $stmt = $this->pdo->prepare(" 
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = :database
              AND table_name = :table
              AND column_name = :column
            LIMIT 1
        ");

        $stmt->execute([
            'database' => $database,
            'table' => $this->table,
            'column' => $column,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Get the current PDO driver name.
     *
     * @return string
     */
    protected function driver(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Return migration names that have already run.
     *
     * @return string[]
     */
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

    public function getRanForRollback(): array
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->query("
        SELECT migration
        FROM {$this->table}
        ORDER BY batch DESC, migration DESC
    ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Return migration names from the latest batch.
     *
     * @return string[]
     */
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

    /**
     * Get the next batch number.
     *
     * @return int
     */
    public function getNextBatchNumber(): int
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->query("SELECT MAX(batch) FROM {$this->table}");

        return ((int) $stmt->fetchColumn()) + 1;
    }

    /**
     * Record an executed migration.
     *
     * @param string $migration Migration name.
     * @param int $batch Batch number.
     * @param string|null $checksum File checksum used for diagnostics.
     *
     * @return void
     */
    public function log(string $migration, int $batch, ?string $checksum = null): void
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->prepare(" 
            INSERT INTO {$this->table} (migration, batch, checksum)
            VALUES (:migration, :batch, :checksum)
        ");

        $stmt->execute([
            'migration' => $migration,
            'batch' => $batch,
            'checksum' => $checksum,
        ]);
    }

    /**
     * Delete an executed migration record.
     *
     * @param string $migration Migration name.
     *
     * @return void
     */
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

    /**
     * Delete a migration record without running its down method.
     *
     * @param string $migration Migration name.
     *
     * @return void
     */
    public function forget(string $migration): void
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE migration = :migration"
        );

        $stmt->execute([
            'migration' => $migration,
        ]);
    }

    /**
     * Get the stored checksum for a migration.
     *
     * @param string $migration Migration name.
     *
     * @return string|null
     */
    public function checksumOf(string $migration): ?string
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->prepare(" 
            SELECT checksum
            FROM {$this->table}
            WHERE migration = :migration
            LIMIT 1
        ");

        $stmt->execute([
            'migration' => $migration,
        ]);

        $checksum = $stmt->fetchColumn();

        return $checksum !== false && $checksum !== ''
            ? (string) $checksum
            : null;
    }

    /**
     * Update the stored checksum for a migration.
     *
     * @param string $migration Migration name.
     * @param string $checksum New checksum.
     *
     * @return void
     */
    public function updateChecksum(string $migration, string $checksum): void
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->prepare(
            "UPDATE {$this->table}
         SET checksum = :checksum
         WHERE migration = :migration"
        );

        $stmt->execute([
            'migration' => $migration,
            'checksum' => $checksum,
        ]);
    }

    /**
     * Return all executed migration records.
     *
     * @return array<int,array<string,mixed>>
     */
    public function all(): array
    {
        $this->ensureTableExists();

        $stmt = $this->pdo->query(" 
            SELECT migration, batch, checksum, executed_at
            FROM {$this->table}
            ORDER BY batch ASC, migration ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
