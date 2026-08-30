<?php

namespace Eril\Migraw\Migration;

use Eril\Migraw\Templates\FluentMigrationTemplate;
use Eril\Migraw\Templates\RawMigrationTemplate;
use RuntimeException;

/**
 * Creates timestamped migration files.
 */
class MigrationCreator
{
    public function __construct(
        protected string $path,
        protected string $driver = 'mysql',
        protected string $template = 'raw'
    ) {
        $this->template = strtolower(trim($this->template));

        if (! in_array($this->template, ['fluent', 'raw'], true)) {
            throw new RuntimeException(
                "Invalid migration template [{$this->template}]. "
                . 'Supported templates: fluent, raw.'
            );
        }
    }

    /**
     * Create a migration file.
     *
     * --sql always forces a blank raw migration.
     * --populate always creates a PopulatorMigration.
     *
     * Otherwise, the configured default template is used.
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

            // Explicit --sql always means blank/raw.
            $sql => (new RawMigrationTemplate(
                $this->driver
            ))->blank(),

            $this->template === 'raw' => (
                new RawMigrationTemplate($this->driver)
            )->make($name),

            default => (
                new FluentMigrationTemplate($this->driver)
            )->make($name),
        };

        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(
                "Unable to create migration file: {$path}"
            );
        }

        return $path;
    }

    /**
     * Normalize a migration name.
     */
    protected function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));

        $name = preg_replace(
            '/[^a-z0-9]+/',
            '_',
            $name
        );

        $name = trim((string) $name, '_');

        if ($name === '') {
            throw new RuntimeException(
                'Migration name cannot be empty.'
            );
        }

        return $name;
    }

    /**
     * Create a population migration stub.
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