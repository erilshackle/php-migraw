<?php

namespace Eril\Migraw\Core;

use PDO;
use RuntimeException;

final class ConnectionResolver
{
    public function __construct(
        protected PathResolver $paths
    ) {}

    public function resolve(array $config): PDO
    {
        $pdo = $config['pdo'] ?? $config['connection'] ?? null;

        if (is_callable($pdo)) {
            $pdo = $pdo();
        }

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        if (! is_array($pdo)) {
            throw new RuntimeException(
                'Database connection must be a PDO instance, callable, or connection array.'
            );
        }

        return $this->fromArray($pdo, $config['options'] ?? []);
    }

    protected function fromArray(array $connection, array $options = []): PDO
    {
        $driver = $connection['driver'] ?? 'mysql';

        $options = $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = match ($driver) {
            'sqlite' => 'sqlite:' . $this->paths->resolve(
                $connection['sqlite_path'] ?? 'database/database.sqlite'
            ),

            'pgsql' => sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $connection['host'] ?? '127.0.0.1',
                $connection['port'] ?? '5432',
                $connection['database'] ?? ''
            ),

            'mysql' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $connection['host'] ?? '127.0.0.1',
                $connection['port'] ?? '3306',
                $connection['database'] ?? '',
                $connection['charset'] ?? 'utf8mb4'
            ),
            
            'sqlsrv' => sprintf(
                'sqlsrv:Server=%s;port=%s;Database=%s',
                $connection['host'] ?? '127.0.0.1',
                $connection['port'] ?? '3306',
                $connection['database'] ?? '',
            ),

            default => throw new RuntimeException("Unsupported database driver: {$driver}"),
        };

        $username = $driver === 'sqlite' ? null : ($connection['username'] ?? null);
        $password = $driver === 'sqlite' ? null : ($connection['password'] ?? null);

        return new PDO($dsn, $username, $password, $options);
    }
}