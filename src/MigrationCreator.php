<?php

namespace Eril\SqlMigrator;

use RuntimeException;

class MigrationCreator
{
    public function __construct(
        protected string $path
    ) {}

    public function create(string $name): string
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

        file_put_contents($path, $this->stub());

        return $path;
    }

    protected function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        $name = trim($name, '_');

        if ($name === '') {
            throw new RuntimeException('Migration name cannot be empty.');
        }

        return $name;
    }

    protected function stub(): string
    {
        return <<<'PHP'
<?php

use Eril\SqlMigrator\Migration;
use Eril\SqlMigrator\Sql\SqlStatement;
use Eril\SqlMigrator\Sql\Sql;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return <<<SQL
        -- Write your UP SQL here
        SQL;
    }

    public function down(): string|array|SqlStatement
    {
        return <<<SQL
        -- Write your DOWN SQL here
        SQL;
    }
};

PHP;
    }
}
