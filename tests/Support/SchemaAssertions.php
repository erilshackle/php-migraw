<?php

namespace Tests\Support;

use PDO;
use PHPUnit\Framework\Assert;

final class SchemaAssertions
{
    public static function tableExists(PDO $pdo, string $driver, string $table): void
    {
        $exists = match ($driver) {
            'sqlite' => self::sqliteTableExists($pdo, $table),
            'pgsql' => self::pgsqlTableExists($pdo, $table),
            'mysql' => self::mysqlTableExists($pdo, $table),
            default => false,
        };

        Assert::assertTrue($exists, "Expected table [{$table}] to exist.");
    }

    public static function tableMissing(PDO $pdo, string $driver, string $table): void
    {
        $exists = match ($driver) {
            'sqlite' => self::sqliteTableExists($pdo, $table),
            'pgsql' => self::pgsqlTableExists($pdo, $table),
            'mysql' => self::mysqlTableExists($pdo, $table),
            default => true,
        };

        Assert::assertFalse($exists, "Expected table [{$table}] to be missing.");
    }

    protected static function sqliteTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM sqlite_schema WHERE type = 'table' AND name = :table"
        );
        $stmt->execute(['table' => $table]);

        return (bool) $stmt->fetchColumn();
    }

    protected static function pgsqlTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT to_regclass(current_schema() || '.' || :table)"
        );
        $stmt->execute(['table' => $table]);

        return $stmt->fetchColumn() !== null;
    }

    protected static function mysqlTableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table
             LIMIT 1'
        );
        $stmt->execute(['table' => $table]);

        return (bool) $stmt->fetchColumn();
    }
}
