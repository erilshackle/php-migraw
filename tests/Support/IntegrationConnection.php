<?php

namespace Tests\Support;

use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

final class IntegrationConnection
{
    public static function mysql(TestCase $test): PDO
    {
        return self::connect(
            $test,
            'MIGRAW_TEST_MYSQL_DSN',
            'MIGRAW_TEST_MYSQL_USER',
            'MIGRAW_TEST_MYSQL_PASSWORD',
            'pdo_mysql'
        );
    }

    public static function pgsql(TestCase $test): PDO
    {
        return self::connect(
            $test,
            'MIGRAW_TEST_PGSQL_DSN',
            'MIGRAW_TEST_PGSQL_USER',
            'MIGRAW_TEST_PGSQL_PASSWORD',
            'pdo_pgsql'
        );
    }

    protected static function connect(
        TestCase $test,
        string $dsnEnv,
        string $userEnv,
        string $passwordEnv,
        string $extension
    ): PDO {
        if (! extension_loaded($extension)) {
            $test->markTestSkipped("Extension {$extension} is not installed.");
        }

        $dsn = getenv($dsnEnv) ?: '';

        if ($dsn === '') {
            $test->markTestSkipped("Environment variable {$dsnEnv} is not configured.");
        }

        try {
            return new PDO(
                $dsn,
                getenv($userEnv) ?: null,
                getenv($passwordEnv) ?: null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (Throwable $e) {
            $test->markTestSkipped('Integration database unavailable: ' . $e->getMessage());
        }
    }
}
