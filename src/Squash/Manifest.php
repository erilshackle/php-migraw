<?php

namespace Eril\Migraw\Squash;


trait Manifest
{
    /**
     * Persist a squash manifest atomically.
     *
     * @param array<string,mixed> $manifest
     */
    protected function writeManifest(
        string $archive,
        array $manifest
    ): string {
        $this->ensureDirectory($archive);

        $file = $archive . DIRECTORY_SEPARATOR . 'manifest.json';

        $temporary = $file . '.tmp';

        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (file_put_contents($temporary, $json . PHP_EOL) === false) {
            throw new \RuntimeException("Unable to write squash manifest: {$file}");
        }

        if (! rename($temporary, $file)) {
            @unlink($temporary);

            throw new \RuntimeException("Unable to finalize squash manifest: {$file}");
        }

        return $file;
    }

    protected function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new \RuntimeException("Unable to create archive directory: {$path}");
        }
    }
}
