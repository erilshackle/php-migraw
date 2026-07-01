<?php

namespace Eril\Migraw\Console;

/**
 * Minimal ANSI color helper for CLI output.
 */
final class Console
{
    /**
     * Color text green.
     *
     * @param string $text Text to color.
     *
     * @return string
     */
    public static function green(string $text): string
    {
        return "\033[32m{$text}\033[0m";
    }

    /**
     * Color text red.
     *
     * @param string $text Text to color.
     *
     * @return string
     */
    public static function red(string $text): string
    {
        return "\033[31m{$text}\033[0m";
    }

    /**
     * Color text yellow.
     *
     * @param string $text Text to color.
     *
     * @return string
     */
    public static function yellow(string $text): string
    {
        return "\033[33m{$text}\033[0m";
    }

    /**
     * Color text cyan.
     *
     * @param string $text Text to color.
     *
     * @return string
     */
    public static function cyan(string $text): string
    {
        return "\033[36m{$text}\033[0m";
    }

    /**
     * Color text gray.
     *
     * @param string $text Text to color.
     *
     * @return string
     */
    public static function gray(string $text): string
    {
        return "\033[90m{$text}\033[0m";
    }

    /**
     * Make text bold.
     *
     * @param string $text Text to style.
     *
     * @return string
     */
    public static function bold(string $text): string
    {
        return "\033[1m{$text}\033[0m";
    }
}
