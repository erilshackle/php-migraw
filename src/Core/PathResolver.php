<?php

namespace Eril\Migraw\Core;

final class PathResolver
{
    public function base(string $path = ''): string
    {
        $base = getcwd();

        if ($path === '') {
            return $base;
        }

        return $base . DIRECTORY_SEPARATOR . str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            ltrim($path, '/\\')
        );
    }

    public function resolve(string $path): string
    {
        if ($this->isAbsolute($path)) {
            return $path;
        }

        return $this->base($path);
    }

    public function relative(string $path): string
    {
        return ltrim(
            str_replace((string) getcwd(), '', $path),
            DIRECTORY_SEPARATOR
        );
    }

    public function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
    }
}