<?php

namespace Eril\SqlMigrator;

final class Console
{
    public static function green(string $text): string
    {
        return "\033[32m{$text}\033[0m";
    }

    public static function red(string $text): string
    {
        return "\033[31m{$text}\033[0m";
    }

    public static function yellow(string $text): string
    {
        return "\033[33m{$text}\033[0m";
    }

    public static function cyan(string $text): string
    {
        return "\033[36m{$text}\033[0m";
    }

    public static function gray(string $text): string
    {
        return "\033[90m{$text}\033[0m";
    }

    public static function bold(string $text): string
    {
        return "\033[1m{$text}\033[0m";
    }
}