<?php

namespace Eril\Migraw;

use RuntimeException;

class SeederRunner
{
    public function __construct(
        protected Migrator $migrator,
        protected string $path
    ) {}

    public function run(?string $name = null): array
    {
        $files = $this->getSeederFiles($name);

        $executed = [];

        foreach ($files as $seederName => $file) {
            $seeder = require $file;

            if (! $seeder instanceof Seeder) {
                throw new RuntimeException("Seeder file must return an instance of Seeder: {$file}");
            }

            $this->migrator->runStatements($seeder->run());

            $executed[] = $seederName;
        }

        return $executed;
    }

    protected function getSeederFiles(?string $name = null): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $files = glob(rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.php') ?: [];

        sort($files);

        $mapped = [];

        foreach ($files as $file) {
            $seederName = basename($file, '.php');

            if ($name !== null && $seederName !== $name) {
                continue;
            }

            $mapped[$seederName] = $file;
        }

        return $mapped;
    }
}