<?php

namespace Tests\Integration;

use Eril\Migraw\Migration;
use Eril\Migraw\Migration\MigrationRepository;
use Eril\Migraw\Migration\Migrator;
use Eril\Migraw\Schema\SchemaDumper;
use Eril\Migraw\Squash\MigrationSquasher;
use Eril\Migraw\Squash\MigrationUnsquasher;
use PDO;
use PHPUnit\Framework\TestCase;

final class SquashTest extends TestCase
{
    protected string $path;
    protected PDO $pdo;
    protected MigrationRepository $repository;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed.');
        }

        $this->path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'migraw_test_'
            . bin2hex(random_bytes(5));

        mkdir($this->path, 0775, true);

        $this->pdo = $this->sqlite();
        $this->repository = new MigrationRepository($this->pdo);

        $this->writeMigrations();
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && is_dir($this->path)) {
            $this->removeDirectory($this->path);
        }
    }

    public function test_squash_and_unsquash_workflow(): void
    {
        $migrator = new Migrator(
            $this->pdo,
            $this->path,
            $this->repository
        );

        $original = [
            '2026_01_01_000001_create_roles',
            '2026_01_01_000002_create_users',
            '2026_01_01_000003_populate_roles',
        ];

        self::assertSame($original, $migrator->migrate());

        $this->pdo->exec(
            "INSERT INTO users (role_id, email)
             VALUES (1, 'admin@example.test')"
        );

        $history = $this->repository->getHistory();

        $squasher = new MigrationSquasher(
            $this->path,
            new SchemaDumper($this->pdo, 'migrations'),
            $this->repository
        );

        $result = $squasher->squash('app_schema');

        self::assertFileExists($result['file']);
        self::assertFileExists($result['manifest']);
        self::assertDirectoryExists($result['archive']);
        self::assertCount(2, $result['archived']);
        self::assertCount(1, $result['populators']);

        $manifest = json_decode(
            file_get_contents($result['manifest']),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        self::assertSame('completed', $manifest['status']);
        self::assertSame($history, $manifest['repository']);

        $baseline = $result['migration'];
        $populator = $result['populators'][0];

        self::assertSame(
            [$baseline, $populator],
            $this->repository->getRan()
        );

        // Existing database is not recreated or modified by squash.
        self::assertSame(
            'admin@example.test',
            $this->pdo->query(
                'SELECT email FROM users WHERE id = 1'
            )->fetchColumn()
        );

        // A clean database must be fully rebuildable from baseline + populators.
        $fresh = $this->sqlite();
        $freshRepository = new MigrationRepository($fresh);

        $freshMigrator = new Migrator(
            $fresh,
            $this->path,
            $freshRepository
        );

        self::assertCount(2, $freshMigrator->migrate());

        self::assertSame(
            'Administrator',
            $fresh->query(
                "SELECT name FROM roles WHERE slug = 'admin'"
            )->fetchColumn()
        );

        self::assertSame(
            0,
            (int) $fresh->query(
                'SELECT COUNT(*) FROM users'
            )->fetchColumn()
        );

        $foreignKeys = $fresh->query(
            'PRAGMA foreign_key_list("users")'
        )->fetchAll();

        self::assertSame(
            'roles',
            $foreignKeys[0]['table'] ?? null
        );

        // Restore the migration history.
        $unsquasher = new MigrationUnsquasher(
            $this->path,
            $this->repository
        );

        $unsquashed = $unsquasher->unsquash();

        self::assertSame($baseline, $unsquashed['baseline']);
        self::assertSame(2, $unsquashed['restored']);
        self::assertSame(1, $unsquashed['populators']);

        self::assertSame($original, $this->repository->getRan());
        self::assertSame($history, $this->repository->getHistory());

        foreach ($original as $migration) {
            self::assertFileExists(
                $this->path
                . DIRECTORY_SEPARATOR
                . $migration
                . '.php'
            );
        }

        self::assertFileDoesNotExist($result['file']);

        // Unsquash does not modify the current database schema/data.
        self::assertSame(
            'admin@example.test',
            $this->pdo->query(
                'SELECT email FROM users WHERE id = 1'
            )->fetchColumn()
        );

        $manifest = json_decode(
            file_get_contents($result['manifest']),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        self::assertSame('unsquashed', $manifest['status']);
    }

    public function test_existing_up_to_date_database_adopts_baseline(): void
    {
        /*
         * Simulate production before squash.
         */
        $production = $this->sqlite();
        $productionRepository = new MigrationRepository($production);

        $productionMigrator = new Migrator(
            $production,
            $this->path,
            $productionRepository
        );

        self::assertCount(3, $productionMigrator->migrate());

        $production->exec(
            "INSERT INTO users (role_id, email)
             VALUES (1, 'production@example.test')"
        );

        /*
         * Development reaches the same state and creates the squash.
         */
        (new Migrator(
            $this->pdo,
            $this->path,
            $this->repository
        ))->migrate();

        $result = (new MigrationSquasher(
            $this->path,
            new SchemaDumper($this->pdo, 'migrations'),
            $this->repository
        ))->squash('app_schema');

        /*
         * Production receives the squashed migration directory.
         *
         * Its old history already represents exactly the state captured
         * by the baseline, so the baseline must be adopted without up().
         */
        $migrated = (new Migrator(
            $production,
            $this->path,
            $productionRepository
        ))->migrate();

        self::assertSame([], $migrated);

        self::assertSame(
            [
                $result['migration'],
                $result['populators'][0],
            ],
            $productionRepository->getRan()
        );

        /*
         * Existing schema/data must remain untouched.
         *
         * If baseline up() had been executed, CREATE TABLE would fail
         * because these tables already exist.
         */
        self::assertSame(
            'production@example.test',
            $production->query(
                'SELECT email FROM users WHERE id = 1'
            )->fetchColumn()
        );

        self::assertSame(
            'Administrator',
            $production->query(
                "SELECT name FROM roles WHERE slug = 'admin'"
            )->fetchColumn()
        );
    }

    public function test_behind_database_catches_up_before_adopting_baseline(): void
    {
        /*
         * Production only executed the first migration.
         */
        $production = $this->sqlite();
        $productionRepository = new MigrationRepository($production);

        $this->runSingleMigration(
            $production,
            $productionRepository,
            '2026_01_01_000001_create_roles'
        );

        self::assertSame(
            ['2026_01_01_000001_create_roles'],
            $productionRepository->getRan()
        );

        /*
         * Development executes everything and squashes.
         */
        (new Migrator(
            $this->pdo,
            $this->path,
            $this->repository
        ))->migrate();

        $result = (new MigrationSquasher(
            $this->path,
            new SchemaDumper($this->pdo, 'migrations'),
            $this->repository
        ))->squash('app_schema');

        /*
         * Production now receives the squashed tree.
         *
         * Migraw must run the missing archived migrations first and only
         * then replace the old history with the baseline.
         */
        $migrated = (new Migrator(
            $production,
            $this->path,
            $productionRepository
        ))->migrate();

        self::assertSame(
            [
                '2026_01_01_000002_create_users',
                '2026_01_01_000003_populate_roles',
            ],
            $migrated
        );

        self::assertSame(
            [
                $result['migration'],
                $result['populators'][0],
            ],
            $productionRepository->getRan()
        );

        self::assertTrue(
            $this->tableExists($production, 'users')
        );

        self::assertSame(
            'Administrator',
            $production->query(
                "SELECT name FROM roles WHERE slug = 'admin'"
            )->fetchColumn()
        );
    }

    public function test_pretend_simulates_catch_up_without_modifying_database_or_repository(): void
    {
        /*
         * Production is behind: only the first migration was executed.
         */
        $production = $this->sqlite();
        $productionRepository = new MigrationRepository($production);

        $this->runSingleMigration(
            $production,
            $productionRepository,
            '2026_01_01_000001_create_roles'
        );

        /*
         * Development completes the old history and squashes it.
         */
        (new Migrator(
            $this->pdo,
            $this->path,
            $this->repository
        ))->migrate();

        (new MigrationSquasher(
            $this->path,
            new SchemaDumper($this->pdo, 'migrations'),
            $this->repository
        ))->squash('app_schema');

        /*
         * Add a normal migration created after the squash.
         */
        $postSquash = '2026_02_01_000001_add_timezone_to_users';

        $this->write(
            $postSquash,
            <<<'PHP'
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return $this->raw(
            "ALTER TABLE users ADD COLUMN timezone TEXT DEFAULT 'UTC';"
        );
    }

    public function down(): string|array|SqlStatement
    {
        return $this->raw(
            'ALTER TABLE users DROP COLUMN timezone;'
        );
    }
};
PHP
        );

        $beforeHistory = $productionRepository->getHistory();

        $migrator = new Migrator(
            $production,
            $this->path,
            $productionRepository
        );

        $migrated = $migrator
            ->pretend()
            ->migrate();

        /*
         * Catch-up migrations and the normal post-squash migration are
         * reported as migrations that would execute.
         */
        self::assertSame(
            [
                '2026_01_01_000002_create_users',
                '2026_01_01_000003_populate_roles',
                $postSquash,
            ],
            $migrated
        );

        /*
         * Pretend must never modify repository history.
         */
        self::assertSame(
            $beforeHistory,
            $productionRepository->getHistory()
        );

        /*
         * Pretend must never modify the actual database.
         */
        self::assertFalse(
            $this->tableExists($production, 'users')
        );

        self::assertSame(
            0,
            (int) $production->query(
                'SELECT COUNT(*) FROM roles'
            )->fetchColumn()
        );

        /*
         * SQL should contain:
         *
         * - archived create_users
         * - archived/preserved population
         * - post-squash migration
         *
         * but not the baseline CREATE TABLE roles because that table
         * already exists and the baseline is only virtually adopted.
         */
        $sql = implode(
            "\n\n",
            $migrator->getPretendedSql()
        );

        self::assertStringContainsString(
            'CREATE TABLE users',
            $sql
        );

        self::assertStringContainsString(
            'Administrator',
            $sql
        );

        self::assertStringContainsString(
            'ALTER TABLE users ADD COLUMN timezone',
            $sql
        );

        self::assertStringNotContainsString(
            'CREATE TABLE roles',
            $sql
        );
    }

    protected function writeMigrations(): void
    {
        $this->write(
            '2026_01_01_000001_create_roles',
            <<<'PHP'
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return $this->raw(<<<'SQL'
CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
);
SQL);
    }

    public function down(): string|array|SqlStatement
    {
        return $this->raw('DROP TABLE IF EXISTS roles;');
    }
};
PHP
        );

        $this->write(
            '2026_01_01_000002_create_users',
            <<<'PHP'
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return $this->raw(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_id INTEGER NOT NULL,
    email TEXT NOT NULL UNIQUE,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);
SQL);
    }

    public function down(): string|array|SqlStatement
    {
        return $this->raw('DROP TABLE IF EXISTS users;');
    }
};
PHP
        );

        $this->write(
            '2026_01_01_000003_populate_roles',
            <<<'PHP'
<?php

use Eril\Migraw\PopulatorMigration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends PopulatorMigration
{
    public function population(): string|array|SqlStatement
    {
        return $this->populate(
            'roles',
            [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug'
        );
    }
};
PHP
        );
    }

    protected function runSingleMigration(
        PDO $pdo,
        MigrationRepository $repository,
        string $migrationName,
        int $batch = 1
    ): void {
        $file = $this->path
            . DIRECTORY_SEPARATOR
            . $migrationName
            . '.php';

        $migration = require $file;

        self::assertInstanceOf(
            Migration::class,
            $migration
        );

        (new Migrator(
            $pdo,
            $this->path,
            $repository
        ))->runStatements(
            $migration->up()
        );

        $checksum = hash_file(
            'sha256',
            $file
        );

        self::assertIsString($checksum);

        $repository->log(
            $migrationName,
            $batch,
            $checksum
        );
    }

    protected function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM sqlite_master
             WHERE type = 'table'
               AND name = :table"
        );

        $stmt->execute([
            'table' => $table,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    protected function write(string $name, string $contents): void
    {
        file_put_contents(
            $this->path
            . DIRECTORY_SEPARATOR
            . $name
            . '.php',
            trim($contents) . PHP_EOL
        );
    }

    protected function sqlite(): PDO
    {
        $pdo = new PDO(
            'sqlite::memory:',
            options: [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    protected function removeDirectory(string $path): void
    {
        foreach (scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $target = $path
                . DIRECTORY_SEPARATOR
                . $item;

            if (is_dir($target)) {
                $this->removeDirectory($target);
            } else {
                @unlink($target);
            }
        }

        @rmdir($path);
    }
}