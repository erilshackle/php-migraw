<?php

namespace Eril\Migraw\Templates;

/**
 * Generates smart raw-SQL migration templates.
 */
class RawMigrationTemplate
{
    public function __construct(
        protected string $driver = 'mysql'
    ) {}

    /**
     * Generate a smart raw migration from its name.
     */
    public function make(string $name): string
    {
        [$up, $down] = $this->resolveTemplate($name);

        return $this->stub($up, $down);
    }

    /**
     * Generate a blank raw SQL migration.
     */
    public function blank(): string
    {
        return $this->stub(
            $this->sqlBlock('-- Write your UP SQL here'),
            $this->sqlBlock('-- Write your DOWN SQL here')
        );
    }

    /**
     * Resolve a smart migration name.
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

        return [
            $this->sqlBlock('-- Write your UP SQL here'),
            $this->sqlBlock('-- Write your DOWN SQL here'),
        ];
    }

    /**
     * @return array<string, callable(array): array{0:string,1:string}>
     */
    protected function templates(): array
    {
        return [
            '/^create_(.+?)(?:_table)?$/'
            => function (array $matches): array {
                $table = $matches[1];

                return [
                    $this->sqlBlock(
                        $this->createTableSql($table)
                    ),

                    $this->sqlBlock(
                        "DROP TABLE IF EXISTS {$table};"
                    ),
                ];
            },

            '/^drop_(.+?)(?:_table)?$/'
            => function (array $matches): array {
                $table = $matches[1];

                return [
                    $this->sqlBlock(
                        "DROP TABLE IF EXISTS {$table};"
                    ),

                    $this->sqlBlock(
                        "-- Recreate {$table} table here"
                    ),
                ];
            },

            '/^rename_(.+)_to_(.+)$/'
            => function (array $matches): array {
                $from = $matches[1];
                $to = $matches[2];

                return [
                    $this->sqlBlock(
                        $this->renameTableSql($from, $to)
                    ),

                    $this->sqlBlock(
                        $this->renameTableSql($to, $from)
                    ),
                ];
            },

            '/^add_(.+)_to_(.+)$/'
            => function (array $matches): array {
                $column = $matches[1];
                $table = $matches[2];

                return [
                    $this->sqlBlock(
                        $this->addColumnSql(
                            $table,
                            $column
                        )
                    ),

                    $this->sqlBlock(
                        $this->dropColumnSql(
                            $table,
                            $column
                        )
                    ),
                ];
            },

            '/^(?:remove|drop)_(.+)_from_(.+)$/'
            => function (array $matches): array {
                $column = $matches[1];
                $table = $matches[2];

                return [
                    $this->sqlBlock(
                        $this->dropColumnSql(
                            $table,
                            $column
                        )
                    ),

                    $this->sqlBlock(
                        $this->addColumnSql(
                            $table,
                            $column
                        )
                    ),
                ];
            },

            '/^create_(.+)_(.+)_index$/'
            => function (array $matches): array {
                $table = $matches[1];
                $column = $matches[2];

                $index = "idx_{$table}_{$column}";

                return [
                    $this->sqlBlock(
                        $this->createIndexSql(
                            $table,
                            $column,
                            $index
                        )
                    ),

                    $this->sqlBlock(
                        $this->dropIndexSql(
                            $index,
                            $table
                        )
                    ),
                ];
            },

            '/^drop_(.+)_(.+)_index$/'
            => function (array $matches): array {
                $table = $matches[1];
                $column = $matches[2];

                $index = "idx_{$table}_{$column}";

                return [
                    $this->sqlBlock(
                        $this->dropIndexSql(
                            $index,
                            $table
                        )
                    ),

                    $this->sqlBlock(
                        $this->createIndexSql(
                            $table,
                            $column,
                            $index
                        )
                    ),
                ];
            },

            '/^create_unique_(.+)_(.+)$/'
            => function (array $matches): array {
                $table = $matches[1];
                $column = $matches[2];

                $name = "uq_{$table}_{$column}";

                return [
                    $this->sqlBlock(
                        $this->createUniqueSql(
                            $table,
                            $column,
                            $name
                        )
                    ),

                    $this->sqlBlock(
                        $this->dropUniqueSql(
                            $table,
                            $name
                        )
                    ),
                ];
            },

            '/^drop_unique_(.+)_(.+)$/'
            => function (array $matches): array {
                $table = $matches[1];
                $column = $matches[2];

                $name = "uq_{$table}_{$column}";

                return [
                    $this->sqlBlock(
                        $this->dropUniqueSql(
                            $table,
                            $name
                        )
                    ),

                    $this->sqlBlock(
                        $this->createUniqueSql(
                            $table,
                            $column,
                            $name
                        )
                    ),
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

    protected function addColumnSql(
        string $table,
        string $column
    ): string {
        return <<<SQL
ALTER TABLE {$table}
            ADD COLUMN {$column} VARCHAR(255) NULL;
SQL;
    }

    protected function dropColumnSql(
        string $table,
        string $column
    ): string {
        return <<<SQL
ALTER TABLE {$table}
            DROP COLUMN {$column};
SQL;
    }

    protected function renameTableSql(
        string $from,
        string $to
    ): string {
        return match ($this->driver()) {
            'pgsql' =>
            "ALTER TABLE {$from} RENAME TO {$to};",

            default =>
            "RENAME TABLE {$from} TO {$to};",
        };
    }

    protected function createIndexSql(
        string $table,
        string $column,
        string $index
    ): string {
        return <<<SQL
CREATE INDEX {$index} ON {$table} ({$column});
SQL;
    }

    protected function dropIndexSql(
        string $index,
        string $table
    ): string {
        return match ($this->driver()) {
            'pgsql', 'sqlite' =>
            "DROP INDEX IF EXISTS {$index};",

            default =>
            "DROP INDEX {$index} ON {$table};",
        };
    }

    protected function createUniqueSql(
        string $table,
        string $column,
        string $name
    ): string {
        return match ($this->driver()) {
            'sqlite' => <<<SQL
CREATE UNIQUE INDEX {$name} ON {$table} ({$column});
SQL,

            default => <<<SQL
ALTER TABLE {$table}
            ADD CONSTRAINT {$name} UNIQUE ({$column});
SQL,
        };
    }

    protected function dropUniqueSql(
        string $table,
        string $name
    ): string {
        return match ($this->driver()) {
            'pgsql' =>
            "ALTER TABLE {$table} DROP CONSTRAINT {$name};",

            'sqlite' =>
            "DROP INDEX IF EXISTS {$name};",

            default =>
            "ALTER TABLE {$table} DROP INDEX {$name};",
        };
    }

    protected function idColumn(): string
    {
        return match ($this->driver()) {
            'sqlite' =>
            'id INTEGER PRIMARY KEY AUTOINCREMENT',

            'pgsql' =>
            'id SERIAL PRIMARY KEY',

            default =>
            'id INT AUTO_INCREMENT PRIMARY KEY',
        };
    }

    protected function createdAtColumn(): string
    {
        return match ($this->driver()) {
            'sqlite' =>
            'created_at TEXT DEFAULT CURRENT_TIMESTAMP',

            'pgsql' =>
            'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',

            default =>
            'created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP',
        };
    }

    protected function updatedAtColumn(): string
    {
        return match ($this->driver()) {
            'sqlite' =>
            'updated_at TEXT DEFAULT CURRENT_TIMESTAMP',

            'pgsql' =>
            'updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP',

            default =>
            'updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP '
                . 'ON UPDATE CURRENT_TIMESTAMP',
        };
    }

    protected function driver(): string
    {
        return strtolower($this->driver);
    }

    protected function sqlBlock(string $sql): string
    {
        $sql = trim($sql);

        return <<<PHP
\$this->raw(<<<'SQL'
        {$sql}
        SQL)
PHP;
    }

    protected function stub(
        string $up,
        string $down
    ): string {
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
