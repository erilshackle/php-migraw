<?php

namespace Eril\Migraw;

use Eril\Migraw\Sql\SqlStatement;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Runs, rolls back and reports SQL-first migrations.
 */
class Migrator
{
    /**
     * PDO driver name.
     */
    protected string $driver;

    /**
     * Whether SQL should be collected instead of executed.
     */
    protected bool $pretending = false;

    /**
     * Force flag reserved for safety checks handled by higher-level commands.
     */
    protected bool $force = false;

    /**
     * @var string[] SQL collected during pretend/dry-run mode.
     */
    protected array $pretendedSql = [];

    /**
     * @param PDO $pdo Database connection.
     * @param string $path Migration directory path.
     * @param MigrationRepository|null $repository Migration repository.
     */
    public function __construct(
        protected PDO $pdo,
        protected string $path,
        protected ?MigrationRepository $repository = null
    ) {
        $this->repository ??= new MigrationRepository($pdo);
        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Enable force mode.
     *
     * Currently Migraw does not block rollback based on checksums. The flag is
     * kept for CLI-level safety behavior and future extension.
     *
     * @return static
     */
    public function force(): static
    {
        $this->force = true;

        return $this;
    }

    /**
     * Run all pending migrations.
     *
     * @return string[] Executed migration names.
     */
    public function migrate(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();
        $pending = array_diff(array_keys($files), $ran);

        if ($pending === []) {
            return [];
        }

        $batch = $this->repository->getNextBatchNumber();
        $executed = [];

        foreach ($pending as $migrationName) {
            $migration = $this->loadMigration($files[$migrationName]);

            $this->runSql($migration->up());

            if (! $this->pretending) {
                $this->repository->log(
                    $migrationName,
                    $batch,
                    $this->checksum($files[$migrationName])
                );
            }

            $executed[] = $migrationName;
        }

        return $executed;
    }

    /**
     * Roll back the latest migration batch.
     *
     * @return string[] Rolled back migration names.
     */
    public function rollback(): array
    {
        $files = $this->getMigrationFiles();
        $lastBatch = $this->repository->getLastBatch();

        if ($lastBatch === []) {
            return [];
        }

        $rolledBack = [];

        foreach ($lastBatch as $migrationName) {
            if (! isset($files[$migrationName])) {
                throw new RuntimeException("Migration file not found: {$migrationName}");
            }

            $migration = $this->loadMigration($files[$migrationName]);

            $this->runSql($migration->down());

            if (! $this->pretending) {
                $this->repository->delete($migrationName);
            }

            $rolledBack[] = $migrationName;
        }

        return $rolledBack;
    }

    /**
     * Return migrations recorded in the repository but missing from disk.
     *
     * @return string[]
     */
    public function missing(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();

        $missing = [];

        foreach ($ran as $migrationName) {
            if (! isset($files[$migrationName])) {
                $missing[] = $migrationName;
            }
        }

        return $missing;
    }

    /**
     * Remove missing migration records from the repository.
     *
     * This does not run down() and does not change the database schema.
     *
     * @return string[] Removed migration names.
     */
    public function repair(): array
    {
        $missing = $this->missing();

        foreach ($missing as $migrationName) {
            $this->repository->forget($migrationName);
        }

        return $missing;
    }

    /**
     * Update stored checksums for modified migrations.
     *
     * This does not run any SQL and does not change the database schema.
     *
     * @return string[] Updated migration names.
     */
    public function repairModified(): array
    {
        $files = $this->getMigrationFiles();
        $modified = $this->modified();

        foreach ($modified as $migrationName) {
            $this->repository->updateChecksum(
                $migrationName,
                $this->checksum($files[$migrationName])
            );
        }

        return $modified;
    }

    /**
     * Return migrations that were modified after being executed.
     *
     * @return string[]
     */
    public function modified(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();

        $modified = [];

        foreach ($ran as $migrationName) {
            if (! isset($files[$migrationName])) {
                continue;
            }

            $stored = $this->repository->checksumOf($migrationName);
            $current = $this->checksum($files[$migrationName]);

            if ($stored !== null && $stored !== $current) {
                $modified[] = $migrationName;
            }
        }

        return $modified;
    }

    /**
     * Return status information for all migration files.
     *
     * A migration is marked as modified when its stored checksum differs from
     * the current file checksum. This is diagnostic only and does not block
     * rollback.
     *
     * @return array<int,array{migration:string,status:string}>
     */
    public function status(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();

        $status = [];

        foreach ($ran as $migrationName) {
            if (! isset($files[$migrationName])) {
                $status[] = [
                    'migration' => $migrationName,
                    'status' => 'missing',
                ];
            }
        }

        foreach ($files as $migrationName => $file) {
            if (! in_array($migrationName, $ran, true)) {
                $status[] = [
                    'migration' => $migrationName,
                    'status' => 'pending',
                ];

                continue;
            }

            $stored = $this->repository->checksumOf($migrationName);
            $current = $this->checksum($file);

            $status[] = [
                'migration' => $migrationName,
                'status' => $stored !== null && $stored !== $current
                    ? 'modified'
                    : 'ran',
            ];
        }


        return $status;
    }

    /**
     * Return pending migration names.
     *
     * @return string[]
     */
    public function pending(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();

        $pending = [];

        foreach ($files as $migrationName => $file) {
            if (! in_array($migrationName, $ran, true)) {
                $pending[] = $migrationName;
            }
        }

        return $pending;
    }


    /**
     * Enable dry-run mode.
     *
     * @return static
     */
    public function pretend(): static
    {
        $this->pretending = true;
        $this->pretendedSql = [];

        return $this;
    }

    /**
     * Get SQL collected during dry-run mode.
     *
     * @return string[]
     */
    public function getPretendedSql(): array
    {
        return $this->pretendedSql;
    }

    /**
     * Roll back all executed migrations.
     *
     * Foreign key checks are temporarily suspended because every migration managed
     * by Migraw is expected to be removed.
     *
     * @return string[] Rolled back migration names.
     */
    public function reset(): array
    {
        return $this->withoutForeignKeyChecks(
            fn(): array => $this->performReset()
        );
    }

    /**
     * Perform the actual reset operation.
     *
     * @return string[] Rolled back migration names.
     */
    protected function performReset(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRanForRollback();

        $rolledBack = [];

        foreach ($ran as $migrationName) {
            if (! isset($files[$migrationName])) {
                throw new RuntimeException(
                    "Migration file not found: {$migrationName}"
                );
            }

            $migration = $this->loadMigration($files[$migrationName]);

            $this->runSql($migration->down());

            if (! $this->pretending) {
                $this->repository->delete($migrationName);
            }

            $rolledBack[] = $migrationName;
        }

        return $rolledBack;
    }

    /**
     * Roll back all managed migrations and run them again.
     *
     * @return array{rolled_back:string[],migrated:string[]}
     */
    public function refresh(): array
    {
        return [
            'rolled_back' => $this->reset(),
            'migrated' => $this->migrate(),
        ];
    }

    /**
     * Drop all database tables and run all migrations again.
     *
     * Migration down() methods are not executed.
     *
     * @return array{dropped:string[],migrated:string[]}
     */
    public function fresh(): array
    {
        if ($this->pretending) {
            throw new RuntimeException(
                'Fresh dry-run is not currently supported.'
            );
        }

        $dropped = (new DatabaseCleaner($this->pdo))
            ->dropAllTables();

        $this->repository = new MigrationRepository($this->pdo);

        return [
            'dropped' => $dropped,
            'migrated' => $this->migrate(),
        ];
    }

    /**
     * Return migration files mapped by migration name.
     *
     * @return array<string,string>
     */
    protected function getMigrationFiles(): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $files = glob(rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];

        sort($files);

        $mapped = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            $mapped[$name] = $file;
        }

        return $mapped;
    }

    /**
     * Load a migration file.
     *
     * @param string $file Migration file path.
     *
     * @return Migration
     */
    protected function loadMigration(string $file): Migration
    {
        $migration = require $file;

        if (! $migration instanceof Migration) {
            throw new RuntimeException("Migration file must return an instance of Migration: {$file}");
        }

        return $migration;
    }

    /**
     * Determine whether schema transactions are reliable for the current driver.
     *
     * @return bool
     */
    protected function supportsSchemaTransactions(): bool
    {
        return match ($this->driver) {
            'pgsql', 'sqlite' => true,
            default => false,
        };
    }

    /**
     * Execute an operation while foreign key checks are temporarily disabled.
     *
     * Foreign key checks are always restored, including when the operation throws
     * an exception.
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     */
    protected function withoutForeignKeyChecks(callable $callback): mixed
    {
        if ($this->pretending) {
            $disable = $this->disableForeignKeyChecksSql();
            $enable = $this->enableForeignKeyChecksSql();

            if ($disable !== null) {
                $this->pretendedSql[] = $disable;
            }

            try {
                return $callback();
            } finally {
                if ($enable !== null) {
                    $this->pretendedSql[] = $enable;
                }
            }
        }

        $disable = $this->disableForeignKeyChecksSql();
        $enable = $this->enableForeignKeyChecksSql();

        if ($disable === null || $enable === null) {
            return $callback();
        }

        $this->pdo->exec($disable);

        try {
            return $callback();
        } finally {
            $this->pdo->exec($enable);
        }
    }

    /**
     * Get the SQL used to disable foreign key checks.
     */
    protected function disableForeignKeyChecksSql(): ?string
    {
        return match ($this->driver) {
            'mysql' => 'SET FOREIGN_KEY_CHECKS = 0',
            'sqlite' => 'PRAGMA foreign_keys = OFF',
            default => null,
        };
    }

    /**
     * Get the SQL used to enable foreign key checks.
     */
    protected function enableForeignKeyChecksSql(): ?string
    {
        return match ($this->driver) {
            'mysql' => 'SET FOREIGN_KEY_CHECKS = 1',
            'sqlite' => 'PRAGMA foreign_keys = ON',
            default => null,
        };
    }

    /**
     * Normalize a migration return value into SQL strings.
     *
     * @param string|array<int,string|SqlStatement>|SqlStatement $sql
     *
     * @return string[]
     */
    protected function normalizeStatements(string|SqlStatement|array $sql): array
    {
        $items = is_array($sql)
            ? $sql
            : [$sql];

        $result = [];

        foreach ($items as $item) {
            if ($item instanceof SqlStatement) {
                $result[] = $item->toSql($this->driver);
                continue;
            }

            $result[] = (string) $item;
        }

        return $result;
    }


    /**
     * Execute statements directly.
     *
     * @param string|array<int,string|SqlStatement>|SqlStatement $sql
     *
     * @return void
     */
    public function runStatements(string|array|SqlStatement $sql): void
    {
        $this->runSql($sql);
    }


    /**
     * Execute SQL statements or collect them during dry-run mode.
     *
     * @param string|array<int,string|SqlStatement>|SqlStatement $sql
     *
     * @return void
     */
    protected function runSql(string|array|SqlStatement $sql): void
    {
        $statements = $this->normalizeStatements($sql);

        $useTransaction = ! $this->pretending
            && $this->supportsSchemaTransactions();

        try {
            if ($useTransaction) {
                $this->pdo->beginTransaction();
            }

            foreach ($statements as $statement) {
                $statement = trim($statement);

                if ($statement === '') {
                    continue;
                }

                if ($this->pretending) {
                    $this->pretendedSql[] = $statement;
                    continue;
                }

                $this->pdo->exec($statement);
            }

            if ($useTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($useTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Compute the checksum for a migration file.
     *
     * @param string $file File path.
     *
     * @return string
     */
    protected function checksum(string $file): string
    {
        return hash_file('sha256', $file);
    }
}
