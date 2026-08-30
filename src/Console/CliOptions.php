<?php

namespace Eril\Migraw\Console;

final class CliOptions
{
    public function __construct(
        protected array $argv
    ) {}

    public static function fromArgv(array $argv): self
    {
        return new self($argv);
    }

    public function command(): ?string
    {
        return $this->argv[1] ?? null;
    }

    public function has(string $option): bool
    {
        return in_array($option, $this->argv, true);
    }

    public function hasAny(array $options): bool
    {
        foreach ($options as $option) {
            if ($this->has($option)) {
                return true;
            }
        }

        return false;
    }

    public function migrationName(): ?string
    {
        foreach (array_slice($this->argv, 2) as $arg) {
            if (str_starts_with($arg, '-')) {
                continue;
            }

            return $arg;
        }

        return null;
    }
}