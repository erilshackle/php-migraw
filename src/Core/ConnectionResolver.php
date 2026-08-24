<?php

namespace Eril\Migraw\Core;

use PDO;
use RuntimeException;
use Throwable;

final class ConnectionResolver
{
    public function __construct(
        protected PathResolver $paths
    ) {}

    /**
     * Resolve the configured database connection to PDO.
     *
     * Supported values:
     *
     * - PDO instance;
     * - Closure returning PDO;
     * - callable returning PDO;
     * - object exposing getPdo();
     * - object exposing pdo();
     * - connection configuration array.
     *
     * @param array<string, mixed> $config
     */
    public function resolve(array $config): PDO
    {
        $connection = $config['connection']
            ?? $config['pdo']
            ?? null;

        if ($connection === null) {
            throw new RuntimeException(
                'Database connection is not configured.'
            );
        }

        return $this->resolveConnection(
            $connection,
            $config['options'] ?? []
        );
    }

    /**
     * Resolve any supported connection representation.
     *
     * @param mixed $connection
     * @param array<int|string, mixed> $options
     */
    protected function resolveConnection(
        mixed $connection,
        array $options = []
    ): PDO {
        if ($connection instanceof PDO) {
            return $connection;
        }

        if (is_callable($connection)) {
            try {
                $resolved = $connection();
            } catch (Throwable $e) {
                throw new RuntimeException(
                    'Database connection callback failed: '
                    . $e->getMessage(),
                    0,
                    $e
                );
            }

            /*
             * Resolve the returned value again.
             *
             * This allows callbacks to return:
             * - PDO
             * - another callable
             * - a supported connection object
             * - a connection array
             */
            return $this->resolveConnection(
                $resolved,
                $options
            );
        }

        if (is_object($connection)) {
            return $this->fromObject($connection);
        }

        if (is_array($connection)) {
            return $this->fromArray(
                $connection,
                $options
            );
        }

        throw new RuntimeException(
            'Database connection must be a PDO instance, '
            . 'callable, supported connection object, '
            . 'or connection array.'
        );
    }

    /**
     * Resolve PDO from a database connection object.
     */
    protected function fromObject(object $connection): PDO
    {
        /*
         * Illuminate\Database\Connection and similar wrappers.
         */
        if (method_exists($connection, 'getPdo')) {
            $pdo = $connection->getPdo();

            if ($pdo instanceof PDO) {
                return $pdo;
            }
        }

        /*
         * Generic/custom wrappers exposing pdo().
         */
        if (method_exists($connection, 'pdo')) {
            $pdo = $connection->pdo();

            if ($pdo instanceof PDO) {
                return $pdo;
            }
        }

        throw new RuntimeException(
            sprintf(
                'Unsupported database connection object [%s]. '
                . 'Expected PDO or an object exposing getPdo() or pdo().',
                $connection::class
            )
        );
    }

    /**
     * Create a PDO connection from configuration.
     *
     * @param array<string, mixed> $connection
     * @param array<int|string, mixed> $options
     */
    protected function fromArray(
        array $connection,
        array $options = []
    ): PDO {
        $driver = strtolower(
            (string) ($connection['driver'] ?? 'mysql')
        );

        $options = $options + [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $dsn = match ($driver) {
            'sqlite' => $this->sqliteDsn($connection),

            'pgsql' => $this->pgsqlDsn($connection),

            'mysql', 'mariadb' => $this->mysqlDsn($connection),

            'sqlsrv' => $this->sqlsrvDsn($connection),

            default => throw new RuntimeException(
                "Unsupported database driver: {$driver}"
            ),
        };

        $username = $driver === 'sqlite'
            ? null
            : ($connection['username'] ?? null);

        $password = $driver === 'sqlite'
            ? null
            : ($connection['password'] ?? null);

        try {
            return new PDO(
                $dsn,
                $username,
                $password,
                $options
            );
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Unable to connect using driver [{$driver}]: "
                . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Build a SQLite DSN.
     *
     * @param array<string, mixed> $connection
     */
    protected function sqliteDsn(array $connection): string
    {
        $path = $connection['sqlite_path']
            ?? 'database/database.sqlite';

        return 'sqlite:' . $this->paths->resolve(
            (string) $path
        );
    }

    /**
     * Build a PostgreSQL DSN.
     *
     * @param array<string, mixed> $connection
     */
    protected function pgsqlDsn(array $connection): string
    {
        return sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $connection['host'] ?? '127.0.0.1',
            $connection['port'] ?? '5432',
            $connection['database'] ?? ''
        );
    }

    /**
     * Build a MySQL/MariaDB DSN.
     *
     * @param array<string, mixed> $connection
     */
    protected function mysqlDsn(array $connection): string
    {
        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $connection['host'] ?? '127.0.0.1',
            $connection['port'] ?? '3306',
            $connection['database'] ?? '',
            $connection['charset'] ?? 'utf8mb4'
        );
    }

    /**
     * Build a SQL Server DSN.
     *
     * @param array<string, mixed> $connection
     * @todo support sql server database connection
     */
    protected function sqlsrvDsn(array $connection): string
    {
        $server = (string) (
            $connection['host']
            ?? '127.0.0.1'
        );

        $port = $connection['port'] ?? null;

        if ($port !== null && $port !== '') {
            $server .= ',' . $port;
        }

        return sprintf(
            'sqlsrv:Server=%s;Database=%s',
            $server,
            $connection['database'] ?? ''
        );
    }
}