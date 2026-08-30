<?php

namespace Eril\Migraw\Schema;

use Eril\Migraw\Migration\MigrationRepository;
use RuntimeException;
use Throwable;

/**
 * Creates and adopts a migration baseline from an existing database schema.
 */
final class Baseline
{
    public function __construct(
        protected string $path,
        protected SchemaDumper $dumper,
        protected MigrationRepository $repository
    ) {}

    /**
     * Create a baseline migration from the current database schema and mark it
     * as already executed without modifying the existing application schema.
     *
     * @return array{
     *     migration:string,
     *     file:string,
     *     checksum:string,
     *     tables:string[]
     * }
     */
    public function create(string $name = 'baseline'): array
    {
        $name = $this->normalizeName($name);

        $history = $this->repository->getHistory();

        if ($history !== []) {
            throw new RuntimeException(
                'Cannot create a baseline because migration history already exists.'
            );
        }

        $files = $this->migrationFiles();

        if ($files !== []) {
            throw new RuntimeException(
                "Cannot create a baseline while migration files already exist:\n  - "
                . implode("\n  - ", array_keys($files))
            );
        }

        $schema = $this->dumper->dump();

        if ($schema === []) {
            throw new RuntimeException(
                'No application tables found to create a baseline.'
            );
        }

        $migration = date('Y_m_d_His') . '_' . $name;
        $file = $this->pathFor($migration);

        $this->ensureMigrationDirectory();

        if (file_exists($file)) {
            throw new RuntimeException(
                "Migration already exists: {$file}"
            );
        }

        $builder = new SchemaMigrationBuilder($this->dumper);

        $source = $builder->build(
            $schema,
            'Baseline generated from an existing database schema.'
        );

        if (file_put_contents($file, $source) === false) {
            throw new RuntimeException(
                "Unable to create baseline migration: {$file}"
            );
        }

        $checksum = hash_file('sha256', $file);

        if (! is_string($checksum)) {
            @unlink($file);

            throw new RuntimeException(
                'Unable to calculate baseline migration checksum.'
            );
        }

        try {
            $this->repository->log(
                $migration,
                1,
                $checksum
            );
        } catch (Throwable $e) {
            @unlink($file);

            throw $e;
        }

        return [
            'migration' => $migration,
            'file' => $file,
            'checksum' => $checksum,
            'tables' => array_keys($schema),
        ];
    }

    /**
     * @return array<string,string>
     */
    protected function migrationFiles(): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $files = glob(
            rtrim($this->path, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . '*.php'
        ) ?: [];

        sort($files);

        $mapped = [];

        foreach ($files as $file) {
            $mapped[basename($file, '.php')] = $file;
        }

        return $mapped;
    }

    protected function ensureMigrationDirectory(): void
    {
        if (is_dir($this->path)) {
            return;
        }

        if (! mkdir($this->path, 0777, true) && ! is_dir($this->path)) {
            throw new RuntimeException(
                "Unable to create migration directory: {$this->path}"
            );
        }
    }

    protected function pathFor(string $migration): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $migration
            . '.php';
    }

    protected function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name) ?? '';
        $name = trim($name, '_');

        if ($name === '') {
            throw new RuntimeException(
                'Baseline migration name cannot be empty.'
            );
        }

        return $name;
    }
}