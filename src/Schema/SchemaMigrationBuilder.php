<?php

namespace Eril\Migraw\Schema;

final class SchemaMigrationBuilder
{
    public function __construct(
        protected SchemaDumper $dumper
    ) {}

    /**
     * Build a migration containing the current database schema.
     *
     * @param array<string, string[]> $schema
     */
    public function build(
        array $schema,
        string $description = 'Schema baseline generated from the current database state.'
    ): string {
        $up = $this->dumper->beforeCreate();

        foreach ($schema as $statements) {
            foreach ($statements as $statement) {
                $up[] = $statement;
            }
        }

        array_push($up, ...$this->dumper->afterCreate());

        $down = $this->dumper->beforeDrop();

        foreach (array_reverse(array_keys($schema)) as $table) {
            $down[] = $this->dumper->dropTable($table);
        }

        array_push($down, ...$this->dumper->afterDrop());

        $upStatements = $this->renderStatements($up);
        $downStatements = $this->renderStatements($down);

        return <<<PHP
<?php

use Eril\\Migraw\\Migration;
use Eril\\Migraw\\Sql\\SqlStatement;

/**
 * {$description}
 */
return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return [
{$upStatements}
        ];
    }

    public function down(): string|array|SqlStatement
    {
        return [
{$downStatements}
        ];
    }
};

PHP;
    }

    /**
     * @param string[] $statements
     */
    protected function renderStatements(array $statements): string
    {
        $blocks = [];

        foreach ($statements as $statement) {
            $statement = trim($statement);

            $blocks[] = <<<PHP
            \$this->raw(<<<'SQL'
{$statement}
SQL)
PHP;
        }

        return implode(",\n\n", $blocks) . ',';
    }
}