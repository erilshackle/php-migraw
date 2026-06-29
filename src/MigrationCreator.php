<?php

namespace Eril\Migraw;

use RuntimeException;

/**
 * Creates timestamped migration files from driver-aware SQL stubs.
 */
class MigrationCreator
{
    /**
     * @param string $path Migration directory path.
     * @param string $driver Database driver name.
     */
    public function __construct(
        protected string $path,
        protected string $driver = 'mysql'
    ) {}

    /**
     * Create a new migration file.
     *
     * @param string $name Migration name.
     *
     * @return string Created file path.
     */
    public function create(string $name, bool $template = false): string
    {
        if (! is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }

        $name = $this->normalizeName($name);
        $timestamp = date('Y_m_d_His');

        $filename = "{$timestamp}_{$name}.php";
        $path = rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($path)) {
            throw new RuntimeException("Migration already exists: {$path}");
        }

        [$up, $down] = $template
            ? $this->resolveTemplate($name)
            : $this->rawSqlTemplate();

        file_put_contents($path, $this->stub($up, $down));

        return $path;
    }

    /**
     * Normalize a migration name for file creation.
     *
     * @param string $name Raw migration name.
     *
     * @return string
     */
    protected function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        $name = trim((string) $name, '_');

        if ($name === '') {
            throw new RuntimeException('Migration name cannot be empty.');
        }

        return $name;
    }

    /**
     * Resolve a migration template from its normalized name.
     *
     * @param string $name Normalized migration name.
     *
     * @return array{0:string,1:string}
     */
    protected function resolveTemplate(string $name): array
    {
        foreach ($this->templates() as $pattern => $resolver) {
            if (preg_match($pattern, $name, $matches)) {
                return $resolver($matches);
            }
        }

        return $this->rawSqlTemplate();
    }

    /**
     * Registered migration name patterns.
     *
     * @return array<string, callable(array<int|string,string>): array{0:string,1:string}>
     */
    protected function templates(): array
    {
        return [
            '/^create_(.+?)(?:_table)?$/' => function (array $matches): array {
                $table = $matches[1];

                return [
                    $this->sqlBlock($this->createTableSql($table)),
                    $this->sqlBlock("DROP TABLE IF EXISTS {$table};"),
                ];
            },

            '/^drop_(.+?)(?:_table)?$/' => function (array $matches): array {
                $table = $matches[1];

                return [
                    $this->sqlBlock("DROP TABLE IF EXISTS {$table};"),
                    $this->sqlBlock("-- Recreate {$table} table here"),
                ];
            },

            '/^rename_(.+)_to_(.+)$/' => function (array $matches): array {
                $from = $matches[1];
                $to = $matches[2];

                return [
                    $this->sqlBlock($this->renameTableSql($from, $to)),
                    $this->sqlBlock($this->renameTableSql($to, $from)),
                ];
            },

            '/^add_(.+)_to_(.+)$/' => function (array $matches): array {
                $column = $matches[1];
                $table = $matches[2];

                return [
                    $this->sqlBlock($this->addColumnSql($table, $column)),
                    $this->sqlBlock($this->dropColumnSql($table, $column)),
                ];
            },

            '/^(?:remove|drop)_(.+)_from_(.+)$/' => function (array $matches): array {
                $column = $matches[1];
                $table = $matches[2];

                return [
                    $this->sqlBlock($this->dropColumnSql($table, $column)),
                    $this->sqlBlock($this->addColumnSql($table, $column)),
                ];
            },

            '/^create_(.+)_(.+)_index$/' => function (array $matches): array {
                $table = $matches[1];
                $column = $matches[2];
                $index = "idx_{$table}_{$column}";

                return [
                    $this->sqlBlock($this->createIndexSql($table, $column, $index)),
                    $this->sqlBlock($this->dropIndexSql($index, $table)),
                ];
            },

            '/^drop_(.+)_(.+)_index$/' => function (array $matches): array {
                $table = $matches[1];
                $column = $matches[2];
                $index = "idx_{$table}_{$column}";

                return [
                    $this->sqlBlock($this->dropIndexSql($index, $table)),
                    $this->sqlBlock($this->createIndexSql($table, $column, $index)),
                ];
            },

            '/^create_unique_(.+)_(.+)$/' => function (array $matches): array {
                $table = $matches[1];
                $column = $matches[2];
                $name = "uq_{$table}_{$column}";

                return [
                    $this->sqlBlock($this->createUniqueSql($table, $column, $name)),
                    $this->sqlBlock($this->dropUniqueSql($table, $name)),
                ];
            },

            '/^drop_unique_(.+)_(.+)$/' => function (array $matches): array {
                $table = $matches[1];
                $column = $matches[2];
                $name = "uq_{$table}_{$column}";

                return [
                    $this->sqlBlock($this->dropUniqueSql($table, $name)),
                    $this->sqlBlock($this->createUniqueSql($table, $column, $name)),
                ];
            },
        ];
    }

    /**
     * Build CREATE TABLE SQL.
     *
     * @param string $table Table name.
     *
     * @return string
     */
    protected function createTableSql(string $table): string
    {
        return <<<SQL
        CREATE TABLE {$table} (
            {$this->idColumn()},
            name VARCHAR(255) NULL,
            {$this->createdAtColumn()},
            {$this->updatedAtColumn()}
        );
SQL;
    }

    /**
     * Build ALTER TABLE ADD COLUMN SQL.
     *
     * @param string $table Table name.
     * @param string $column Column name.
     *
     * @return string
     */
    protected function addColumnSql(string $table, string $column): string
    {
        return <<<SQL
        ALTER TABLE {$table}
            ADD COLUMN {$column} VARCHAR(255) NULL;
SQL;
    }

    /**
     * Build ALTER TABLE DROP COLUMN SQL.
     *
     * @param string $table Table name.
     * @param string $column Column name.
     *
     * @return string
     */
    protected function dropColumnSql(string $table, string $column): string
    {
        return <<<SQL
        ALTER TABLE {$table}
            DROP COLUMN {$column};
SQL;
    }

    /**
     * Build rename table SQL.
     *
     * @param string $from Current table name.
     * @param string $to New table name.
     *
     * @return string
     */
    protected function renameTableSql(string $from, string $to): string
    {
        return match ($this->driver()) {
            'pgsql' => "ALTER TABLE {$from} RENAME TO {$to};",
            default => "RENAME TABLE {$from} TO {$to};",
        };
    }

    /**
     * Build CREATE INDEX SQL.
     *
     * @param string $table Table name.
     * @param string $column Column name.
     * @param string $index Index name.
     *
     * @return string
     */
    protected function createIndexSql(string $table, string $column, string $index): string
    {
        return <<<SQL
        CREATE INDEX {$index}
        ON {$table} ({$column});
SQL;
    }

    /**
     * Build DROP INDEX SQL.
     *
     * @param string $index Index name.
     * @param string $table Table name.
     *
     * @return string
     */
    protected function dropIndexSql(string $index, string $table): string
    {
        return match ($this->driver()) {
            'pgsql' => "DROP INDEX IF EXISTS {$index};",
            'sqlite' => "DROP INDEX IF EXISTS {$index};",
            default => "DROP INDEX {$index} ON {$table};",
        };
    }

    /**
     * Build unique constraint SQL.
     *
     * @param string $table Table name.
     * @param string $column Column name.
     * @param string $name Constraint/index name.
     *
     * @return string
     */
    protected function createUniqueSql(string $table, string $column, string $name): string
    {
        return match ($this->driver()) {
            'sqlite' => <<<SQL
        CREATE UNIQUE INDEX {$name}
        ON {$table} ({$column});
SQL,
            default => <<<SQL
        ALTER TABLE {$table}
            ADD CONSTRAINT {$name} UNIQUE ({$column});
SQL,
        };
    }

    /**
     * Build drop unique constraint SQL.
     *
     * @param string $table Table name.
     * @param string $name Constraint/index name.
     *
     * @return string
     */
    protected function dropUniqueSql(string $table, string $name): string
    {
        return match ($this->driver()) {
            'pgsql' => "ALTER TABLE {$table} DROP CONSTRAINT {$name};",
            'sqlite' => "DROP INDEX IF EXISTS {$name};",
            default => "ALTER TABLE {$table} DROP INDEX {$name};",
        };
    }

    /**
     * Return an ID column definition for the current driver.
     *
     * @return string
     */
    protected function idColumn(): string
    {
        return match ($this->driver()) {
            'sqlite' => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
            'pgsql' => 'id SERIAL PRIMARY KEY',
            default => 'id INT AUTO_INCREMENT PRIMARY KEY',
        };
    }

    /**
     * Return a created_at column definition for the current driver.
     *
     * @return string
     */
    protected function createdAtColumn(): string
    {
        return match ($this->driver()) {
            'sqlite' => 'created_at TEXT DEFAULT CURRENT_TIMESTAMP',
            'pgsql' => 'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            default => 'created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
        };
    }

    /**
     * Return an updated_at column definition for the current driver.
     *
     * @return string
     */
    protected function updatedAtColumn(): string
    {
        return match ($this->driver()) {
            'sqlite' => 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP',
            'pgsql' => 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            default => 'updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        };
    }

    /**
     * Normalize the current driver name.
     *
     * @return string
     */
    protected function driver(): string
    {
        return strtolower($this->driver);
    }

    /**
     * Return the fallback raw SQL template.
     *
     * @return array{0:string,1:string}
     */
    protected function rawSqlTemplate(): array
    {
        return [
            $this->sqlBlock('-- Write your UP SQL here'),
            $this->sqlBlock('-- Write your DOWN SQL here'),
        ];
    }

    /**
     * Wrap SQL in a heredoc return expression.
     *
     * @param string $sql SQL contents.
     *
     * @return string
     */
    protected function sqlBlock(string $sql): string
    {
        $sql = trim($sql);

        return <<<PHP
<<<SQL
        {$sql}
        SQL
PHP;
    }

    /**
     * Build the final migration file contents.
     *
     * @param string $up Up method return expression.
     * @param string $down Down method return expression.
     *
     * @return string
     */
    protected function stub(string $up, string $down): string
    {
        return <<<PHP
<?php

use Eril\\Migraw\\Migration;
use Eril\\Migraw\\Sql\\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return {$up};
    }

    public function down(): string|array|SqlStatement
    {
        return {$down};
    }
};

PHP;
    }
}
