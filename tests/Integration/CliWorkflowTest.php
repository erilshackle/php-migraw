<?php

namespace Tests\Integration;

use Eril\Migraw\Migration\MigrationRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class CliWorkflowTest extends TestCase
{
    protected string $path;

    protected string $database;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed.');
        }

        $this->path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'migraw_cli_workflow_'
            . bin2hex(random_bytes(5));

        mkdir($this->path, 0775, true);

        $this->database = $this->path
            . DIRECTORY_SEPARATOR
            . 'database'
            . DIRECTORY_SEPARATOR
            . 'database.sqlite';
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && is_dir($this->path)) {
            $this->removeDirectory($this->path);
        }
    }

    public function test_cli_commands_progressively_against_one_sqlite_database(): void
    {
        self::assertStringContainsString(
            'Created config: migraw.php',
            $this->runMigraw('init')
        );
        self::assertStringContainsString(
            'Created config: migraw.php',
            $this->runMigraw('init:mysql', '--force')
        );
        self::assertStringContainsString(
            'Created config: migraw.php',
            $this->runMigraw('init:pgsql', '--force')
        );
        self::assertStringContainsString(
            'Created config: migraw.php',
            $this->runMigraw('init:sqlite', '--force')
        );
        self::assertStringContainsString(
            'Config file already exists',
            $this->runMigraw('init')
        );

        $pdo = new PDO('sqlite:' . $this->database);
        $pdo->exec(
            'CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL
            )'
        );

        self::assertStringContainsString(
            'Adopted baseline:',
            $this->runMigraw('baseline', 'app_schema', '--force')
        );
        self::assertStringContainsString(
            'Ready.',
            $this->runMigraw('doctor')
        );

        $this->runMigraw('make', 'populate_roles');

        $migrationFiles = glob(
            $this->path
            . DIRECTORY_SEPARATOR
            . 'database'
            . DIRECTORY_SEPARATOR
            . 'migrations'
            . DIRECTORY_SEPARATOR
            . '*.php'
        ) ?: [];

        self::assertCount(2, $migrationFiles);

        $populationFile = array_values(array_filter(
            $migrationFiles,
            static fn(string $file): bool => str_contains(
                basename($file),
                '_populate_roles.php'
            )
        ))[0] ?? null;

        self::assertIsString($populationFile);

        $population = file_get_contents($populationFile);
        self::assertIsString($population);
        $population = str_replace(
            "table: '',",
            "table: 'roles',",
            $population
        );
        $population = str_replace(
            "            rows: [\n                //\n            ],",
            "            rows: [\n"
                . "                ['slug' => 'admin', 'name' => 'Admin'],\n"
                . "            ],",
            $population
        );
        $population = str_replace(
            "uniqueBy: 'id'",
            "uniqueBy: 'slug'",
            $population
        );
        self::assertNotFalse(file_put_contents($populationFile, $population));

        self::assertStringContainsString(
            'All migrations are valid.',
            $this->runMigraw('validate')
        );
        self::assertStringContainsString(
            '[pending]',
            $this->runMigraw('status')
        );
        self::assertStringContainsString('Migraw', $this->runMigraw('help'));

        self::assertStringContainsString(
            'Dry run:',
            $this->runMigraw('migrate', '--dry-run')
        );
        self::assertSame(
            0,
            (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn()
        );
        self::assertStringContainsString(
            'Migrated:',
            $this->runMigraw('migrate')
        );
        self::assertStringContainsString(
            'Nothing to migrate.',
            $this->runMigraw('up')
        );
        self::assertSame(
            1,
            (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn()
        );

        self::assertStringContainsString(
            'No SQL to display.',
            $this->runMigraw('rollback', '--dry-run')
        );
        self::assertStringContainsString(
            'Rolled back:',
            $this->runMigraw('rollback', '--force')
        );
        self::assertStringContainsString(
            'Rolled back:',
            $this->runMigraw('down')
        );
        self::assertStringContainsString(
            'Migrated:',
            $this->runMigraw('up')
        );

        self::assertStringContainsString(
            'Dry run:',
            $this->runMigraw('refresh', '--pretend')
        );
        self::assertStringContainsString(
            'Migrated:',
            $this->runMigraw('refresh', '--force')
        );
        self::assertStringContainsString(
            'Dry run:',
            $this->runMigraw('reset', '--dry-run')
        );
        self::assertStringContainsString(
            'Rolled back:',
            $this->runMigraw('reset', '--force')
        );
        self::assertStringContainsString(
            'Migrated:',
            $this->runMigraw('migrate')
        );
        self::assertStringContainsString(
            'Migrated:',
            $this->runMigraw('fresh')
        );

        file_put_contents(
            $populationFile,
            PHP_EOL . '// accepted checksum change' . PHP_EOL,
            FILE_APPEND
        );
        self::assertStringContainsString(
            'Updated checksum:',
            $this->runMigraw('repair', '--modified', '--force')
        );

        (new MigrationRepository($pdo))->log(
            '2026_01_01_000000_missing_test_migration',
            99,
            str_repeat('0', 64)
        );
        self::assertStringContainsString(
            'Removed: 2026_01_01_000000_missing_test_migration',
            $this->runMigraw('repair', '--force')
        );

        self::assertStringContainsString(
            'Created baseline:',
            $this->runMigraw('squash', 'compact_schema', '--force')
        );
        self::assertStringContainsString(
            'Unsquashed:',
            $this->runMigraw('unsquash', '--force')
        );
        self::assertStringContainsString(
            'All migrations are valid.',
            $this->runMigraw('validate')
        );
        self::assertStringContainsString(
            'Migrated:',
            $this->runMigraw('fresh')
        );
        self::assertSame(
            1,
            (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn()
        );
    }

    protected function runMigraw(string ...$arguments): string
    {
        $root = dirname(__DIR__, 2);
        $binary = $root
            . DIRECTORY_SEPARATOR
            . 'bin'
            . DIRECTORY_SEPARATOR
            . 'migraw';

        $process = proc_open(
            [PHP_BINARY, $binary, ...$arguments],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $this->path
        );

        self::assertIsResource($process);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $output = (string) $stdout . (string) $stderr;

        self::assertSame(
            0,
            $exitCode,
            implode(' ', $arguments) . " failed:\n" . $output
        );

        return $output;
    }

    protected function removeDirectory(string $path): void
    {
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path . DIRECTORY_SEPARATOR . $item;

            if (is_dir($target)) {
                $this->removeDirectory($target);
            } else {
                unlink($target);
            }
        }

        rmdir($path);
    }
}
