<?php

namespace Eril\Migraw;

use RuntimeException;

/**
 * Creates timestamped migration files from driver-aware SQL stubs.
 */
class MigrationCreator
{
    public function __construct(
        protected string $path,
        protected string $driver = 'mysql'
    ) {}

    /**
     * Create a new migration file.
     *
     * By default, Migraw tries to generate a smart migration template
     * whose SQL is wrapped in a RawSql statement.
     *
     * When $sql is true, a blank migration returning plain SQL strings
     * is generated.
     *
     * When $populate is true, a PopulatorMigration is generated.
     *
     * @param string $name     Migration name.
     * @param bool   $sql      Whether to generate a blank plain-SQL migration.
     * @param bool   $populate Whether to generate a population migration.
     *
     * @return string Created migration file path.
     */
    public function create(
        string $name,
        bool $sql = false,
        bool $populate = false
    ): string {
        if ($sql && $populate) {
            throw new RuntimeException(
                'A migration cannot be both SQL and populate.'
            );
        }

        if (! is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }

        $name = $this->normalizeName($name);
        $timestamp = date('Y_m_d_His');

        $filename = "{$timestamp}_{$name}.php";

        $path = rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $filename;

        if (file_exists($path)) {
            throw new RuntimeException(
                "Migration already exists: {$path}"
            );
        }

        $contents = match (true) {
            $populate => $this->populateStub(),

            $sql => $this->migrationStub(
                ...$this->rawSqlTemplate(wrapped: false)
            ),

            default => $this->migrationStub(
                ...$this->resolveTemplate($name)
            ),
        };

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(
                "Unable to create migration file: {$path}"
            );
        }

        return $path;
    }

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

    protected function resolveTemplate(string $name): array
    {
        foreach ($this->templates() as $pattern => $resolver) {
            if (preg_match($pattern, $name, $matches)) {
                return $resolver($matches);
            }
        }

        return $this->rawSqlTemplate();
    }


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

    protected function addColumnSql(string $table, string $column): string
    {
        return <<<SQL
        ALTER TABLE {$table}
            ADD COLUMN {$column} VARCHAR(255) NULL;
SQL;
    }

    protected function dropColumnSql(string $table, string $column): string
    {
        return <<<SQL
        ALTER TABLE {$table}
            DROP COLUMN {$column};
SQL;
    }

    protected function renameTableSql(string $from, string $to): string
    {
        return match ($this->driver()) {
            'pgsql' => "ALTER TABLE {$from} RENAME TO {$to};",
            default => "RENAME TABLE {$from} TO {$to};",
        };
    }

    protected function createIndexSql(string $table, string $column, string $index): string
    {
        return <<<SQL
        CREATE INDEX {$index}
        ON {$table} ({$column});
SQL;
    }

    protected function dropIndexSql(string $index, string $table): string
    {
        return match ($this->driver()) {
            'pgsql', 'sqlite' => "DROP INDEX IF EXISTS {$index};",
            default => "DROP INDEX {$index} ON {$table};",
        };
    }

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

    protected function dropUniqueSql(string $table, string $name): string
    {
        return match ($this->driver()) {
            'pgsql' => "ALTER TABLE {$table} DROP CONSTRAINT {$name};",
            'sqlite' => "DROP INDEX IF EXISTS {$name};",
            default => "ALTER TABLE {$table} DROP INDEX {$name};",
        };
    }

    protected function idColumn(): string
    {
        return match ($this->driver()) {
            'sqlite' => 'id INTEGER PRIMARY KEY AUTOINCREMENT',
            'pgsql' => 'id SERIAL PRIMARY KEY',
            default => 'id INT AUTO_INCREMENT PRIMARY KEY',
        };
    }

    protected function createdAtColumn(): string
    {
        return match ($this->driver()) {
            'sqlite' => 'created_at TEXT DEFAULT CURRENT_TIMESTAMP',
            'pgsql' => 'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            default => 'created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
        };
    }

    protected function updatedAtColumn(): string
    {
        return match ($this->driver()) {
            'sqlite' => 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP',
            'pgsql' => 'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
            default => 'updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        };
    }

    protected function driver(): string
    {
        return strtolower($this->driver);
    }

    /**
     * Build blank UP and DOWN SQL expressions.
     *
     * @param bool $wrapped Whether SQL should be wrapped in $this->raw().
     *
     * @return array{0: string, 1: string}
     */
    protected function rawSqlTemplate($wrapped = true): array
    {
        return [
            $this->sqlBlock('-- Write your UP SQL here', $wrapped),
            $this->sqlBlock('-- Write your DOWN SQL here', $wrapped),
        ];
    }

    /**
     * Build a SQL return expression for a migration method.
     *
     * @param string $sql     SQL body.
     * @param bool   $wrapped Whether SQL should be wrapped in $this->raw().
     *
     * @return string
     */
    protected function sqlBlock(string $sql, $wrapped = true): string
    {
        $sql = trim($sql);

        if ($wrapped) {
            return <<<PHP
            \$this->raw(<<<SQL
                    {$sql}
                    SQL)
            PHP;
        }

        return <<<PHP
            <<<SQL
                    {$sql}
                    SQL
            PHP;
    }

    /**
     * Build a standard migration file.
     *
     * @param string $up   Up method return expression.
     * @param string $down Down method return expression.
     *
     * @return string
     */
    protected function migrationStub(string $up, string $down): string
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

    /**
     * Build a population migration file.
     *
     * The generated migration intentionally omits down(), because
     * PopulatorMigration provides an empty rollback by default.
     *
     * @return string
     */
    protected function populateStub(): string
    {
        return <<<'PHP'
<?php

use Eril\Migraw\PopulatorMigration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends PopulatorMigration
{
    public function population(): string|array|SqlStatement
    {
        return $this->populate(
            table: '',
            rows: [
                //
            ],
            uniqueBy: 'id'
        );
    }
};

PHP;
    }
}
