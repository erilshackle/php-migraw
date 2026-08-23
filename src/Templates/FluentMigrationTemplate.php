<?php

namespace Eril\Migraw\Templates;

/**
 * Generates fluent SqlStatement migration templates.
 */
class FluentMigrationTemplate
{
    public function __construct(
        protected string $driver = 'mysql'
    ) {}

    /**
     * Generate a smart fluent migration from its name.
     */
    public function make(string $name): string
    {
        [$up, $down] = $this->resolveTemplate($name);

        return $this->stub($up, $down);
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

        return $this->blankTemplate();
    }

    /**
     * Fluent smart migration templates.
     *
     * @return array<string, callable(array): array{0:string,1:string}>
     */
    protected function templates(): array
    {
        return [
            '/^create_(.+?)(?:_table)?$/'
                => function (array $matches): array {
                    $table = $matches[1];

                    return [
                        <<<PHP
\$this->create('{$table}')
            ->id()
            ->column('name VARCHAR(255) NULL')
            ->timestamps()
PHP,

                        <<<PHP
\$this->drop('{$table}')
            ->ifExists()
PHP,
                    ];
                },

            '/^drop_(.+?)(?:_table)?$/'
                => function (array $matches): array {
                    $table = $matches[1];

                    return [
                        <<<PHP
\$this->drop('{$table}')
            ->ifExists()
PHP,

                        <<<PHP
\$this->create('{$table}')
            ->id()
            ->column('name VARCHAR(255) NULL')
            ->timestamps()
PHP,
                    ];
                },

            '/^add_(.+)_to_(.+)$/'
                => function (array $matches): array {
                    $column = $matches[1];
                    $table = $matches[2];

                    return [
                        <<<PHP
\$this->alter('{$table}')
            ->add('{$column} VARCHAR(255) NULL')
PHP,

                        <<<PHP
\$this->alter('{$table}')
            ->dropColumn('{$column}')
PHP,
                    ];
                },

            '/^(?:remove|drop)_(.+)_from_(.+)$/'
                => function (array $matches): array {
                    $column = $matches[1];
                    $table = $matches[2];

                    return [
                        <<<PHP
\$this->alter('{$table}')
            ->dropColumn('{$column}')
PHP,

                        <<<PHP
\$this->alter('{$table}')
            ->add('{$column} VARCHAR(255) NULL')
PHP,
                    ];
                },
        ];
    }

    /**
     * Return a blank fluent migration.
     *
     * Unknown names intentionally produce a fluent skeleton instead
     * of silently switching to raw mode.
     *
     * @return array{0:string,1:string}
     */
    protected function blankTemplate(): array
    {
        return [
            <<<'PHP'
$this->alter('table_name')
            // ->add('column VARCHAR(255) NULL')
PHP,

            <<<'PHP'
$this->alter('table_name')
            // ->dropColumn('column')
PHP,
        ];
    }

    /**
     * Build the final fluent migration file.
     */
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