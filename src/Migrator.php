<?php

namespace Eril\Migraw;

use Eril\Migraw\Sql\SqlStatement;
use PDO;
use RuntimeException;
use Throwable;

class Migrator
{

    protected ?string $driver = null;
    protected bool $pretending = false;
    protected bool $force = false;

    protected array $pretendedSql = [];

    public function __construct(
        protected PDO $pdo,
        protected string $path,
        protected ?MigrationRepository $repository = null
    ) {
        $this->repository ??= new MigrationRepository($pdo);
        $this->driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    public function force(): static
    {
        $this->force = true;

        return $this;
    }

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
                    $batch
                );
            }

            $executed[] = $migrationName;
        }

        return $executed;
    }

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

    public function status(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();

        $status = [];

        foreach ($files as $migrationName => $file) {
            $status[] = [
                'migration' => $migrationName,
                'status' => in_array($migrationName, $ran, true) ? 'ran' : 'pending',
            ];
        }

        return $status;
    }

    public function pending(): array
    {
        $files = $this->getMigrationFiles();
        $ran = $this->repository->getRan();

        $pending = [];

        foreach ($files as $migrationName => $file) {
            if (!in_array($migrationName, $ran, true)) {
                $pending[] = $migrationName;
            }
        }

        return $pending;
    }



    public function pretend(): static
    {
        $this->pretending = true;
        $this->pretendedSql = [];

        return $this;
    }

    public function getPretendedSql(): array
    {
        return $this->pretendedSql;
    }

    public function reset(): array
    {
        $files = $this->getMigrationFiles();
        $ran = array_reverse($this->repository->getRan());

        $rolledBack = [];

        foreach ($ran as $migrationName) {
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

    public function fresh(): array
    {
        return $this->refresh();
        // $this->dropAllTables();

        // return [
        //     'migrated' => $this->migrate(),
        // ];
    }

    public function refresh(): array
    {
        return [
            'rolled_back' => $this->reset(),
            'migrated' => $this->migrate(),
        ];
    }

    protected function dropAllTables(): void
    {
        match ($this->driver()) {
            'sqlite' => $this->dropAllSqliteTables(),
            'pgsql' => $this->dropAllPostgresTables(),
            default => $this->dropAllMysqlTables(),
        };
    }

    protected function dropAllMysqlTables(): void
    {
        $database = $this->pdo->query('SELECT DATABASE()')->fetchColumn();

        $stmt = $this->pdo->prepare("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = :database
    ");

        $stmt->execute(['database' => $database]);

        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if ($tables === []) {
            return;
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function dropAllSqliteTables(): void
    {
        $tables = $this->pdo
            ->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }

    protected function dropAllPostgresTables(): void
    {
        $tables = $this->pdo
            ->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
            ->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $this->pdo->exec('DROP TABLE IF EXISTS "' . $table . '" CASCADE');
        }
    }

    protected function driver(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

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


    protected function loadMigration(string $file): Migration
    {
        $migration = require $file;

        if (! $migration instanceof Migration) {
            throw new RuntimeException("Migration file must return an instance of Migration: {$file}");
        }

        return $migration;
    }


    protected function supportsSchemaTransactions(): bool
    {
        return match ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) {
            'pgsql', 'sqlite' => true,
            default => false,
        };
    }

    protected function normalizeStatements(
        string|SqlStatement|array $sql
    ): array {
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
     * @param string|array<string|SqlStatement>|SqlStatement $sql
     * @return void
     */
    public function runStatements(string|array|SqlStatement $sql): void
    {
        $this->runSql($sql);
    }

    /**
     * @param string|array<string|SqlStatement>|SqlStatement $sql
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
}
