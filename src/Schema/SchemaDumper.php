<?php

namespace Eril\Migraw\Schema;

use Eril\Migraw\Schema\Drivers\MySqlSchemaDumper;
use Eril\Migraw\Schema\Drivers\PostgresSchemaDumper;
use Eril\Migraw\Schema\Drivers\SqliteSchemaDumper;
use PDO;
use RuntimeException;

final class SchemaDumper
{
    protected SchemaDriverDumper $dumper;

    public function __construct(
        PDO $pdo,
        string $migrationTable = 'migrations'
    ) {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $this->dumper = match ($driver) {
            'mysql' => new MySqlSchemaDumper($pdo, $migrationTable),
            'sqlite' => new SqliteSchemaDumper($pdo, $migrationTable),
            'pgsql' => new PostgresSchemaDumper($pdo, $migrationTable),
            default => throw new RuntimeException(
                "Schema squash is not supported for driver: {$driver}"
            ),
        };
    }

    /**
     * @return array<string, string[]>
     */
    public function dump(): array
    {
        return $this->dumper->dump();
    }

    public function beforeCreate(): array
    {
        return $this->dumper->beforeCreate();
    }

    public function afterCreate(): array
    {
        return $this->dumper->afterCreate();
    }

    public function beforeDrop(): array
    {
        return $this->dumper->beforeDrop();
    }

    public function afterDrop(): array
    {
        return $this->dumper->afterDrop();
    }

    public function dropTable(string $table): string
    {
        return $this->dumper->dropTable($table);
    }
}