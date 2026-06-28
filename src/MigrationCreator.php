<?php

namespace Eril\Migraw;

use RuntimeException;

/**
 * Creates timestamped migration files from the default Migraw stub.
 */
class MigrationCreator
{
    /**
     * @param string $path Migration directory path.
     */
    public function __construct(
        protected string $path
    ) {}

    /**
     * Create a new migration file.
     *
     * @param string $name Migration name.
     *
     * @return string Created file path.
     */
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
     * Return the default migration stub.
     *
     * @return string
     */
    protected function stub(): string
    {
        return <<<'PHP'
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return $this->create('example')
            ->id()
            ->column('name')
            ->timestamps();
    }

    public function down(): string|array|SqlStatement
    {
        return $this->drop('example', true);
    }
};

PHP;
    }
}
