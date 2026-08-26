<?php

namespace Eril\Migraw\Core;

use Eril\Migraw\MigrationRepository;
use Eril\Migraw\Migrator;
use PDO;

final class RuntimeContext
{
    public function __construct(
        public readonly array $config,
        public readonly PDO $pdo,
        public readonly string $path,
        public readonly string $table,
        public readonly MigrationRepository $repository,
        public readonly Migrator $migrator
    ) {}

    public static function boot(
        Config $configManager,
        ConnectionResolver $connections,
        PathResolver $paths
    ): self {
        $configManager->loadDefaultBootstrap();

        $config = $configManager->load();

        $configManager->loadConfiguredBootstrap($config);

        $pdo = $connections->resolve($config);
        $path = $paths->resolve($config['path'] ?? 'database/migrations');
        $table = $config['table'] ?? 'migrations';

        $repository = new MigrationRepository($pdo, $table);
        $migrator = new Migrator($pdo, $path, $repository);

        return new self(
            config: $config,
            pdo: $pdo,
            path: $path,
            table: $table,
            repository: $repository,
            migrator: $migrator
        );
    }
}