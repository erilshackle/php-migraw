<?php

namespace Tests\Integration;

use Eril\Migraw\Migration\MigrationRepository;
use Eril\Migraw\Migration\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigrationWorkflowTest extends TestCase
{
    protected string $path;
    protected PDO $pdo;
    protected MigrationRepository $repository;
    protected Migrator $migrator;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'migraw_workflow_'
            . bin2hex(random_bytes(5));

        mkdir($this->path, 0775, true);

        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('PRAGMA foreign_keys = ON');

        $this->repository = new MigrationRepository($this->pdo);
        $this->migrator = new Migrator(
            $this->pdo,
            $this->path,
            $this->repository
        );

        $this->writeMigrations();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->path);
    }

    public function test_complete_migration_workflow(): void
    {
        $migrations = [
            '2026_01_01_000001_create_users',
            '2026_01_01_000002_add_active_to_users',
        ];

        self::assertSame($migrations, $this->migrator->pending());

        self::assertSame(
            ['pending', 'pending'],
            array_column($this->migrator->status(), 'status')
        );

        self::assertSame($migrations, $this->migrator->migrate());
        self::assertSame([], $this->migrator->pending());
        self::assertSame($migrations, $this->repository->getRan());

        self::assertSame(
            ['ran', 'ran'],
            array_column($this->migrator->status(), 'status')
        );

        $columns = $this->pdo
            ->query('PRAGMA table_info("users")')
            ->fetchAll();

        self::assertContains('active', array_column($columns, 'name'));

        // Latest batch contains both migrations.
        self::assertSame(
            array_reverse($migrations),
            $this->migrator->rollback()
        );

        self::assertSame([], $this->repository->getRan());

        self::assertSame($migrations, $this->migrator->migrate());

        $refresh = $this->migrator->refresh();

        self::assertSame(
            array_reverse($migrations),
            $refresh['rolled_back']
        );
        self::assertSame($migrations, $refresh['migrated']);

        $fresh = $this->migrator->fresh();

        self::assertContains('users', $fresh['dropped']);
        self::assertSame($migrations, $fresh['migrated']);

        // Modified migration detection + repair.
        $second = $this->path
            . DIRECTORY_SEPARATOR
            . '2026_01_01_000002_add_active_to_users.php';

        file_put_contents($second, PHP_EOL . '// intentionally modified', FILE_APPEND);

        self::assertSame(
            ['2026_01_01_000002_add_active_to_users'],
            $this->migrator->modified()
        );

        self::assertSame(
            ['2026_01_01_000002_add_active_to_users'],
            $this->migrator->repairModified()
        );

        self::assertSame([], $this->migrator->modified());

        // Missing migration detection + repair.
        $first = $this->path
            . DIRECTORY_SEPARATOR
            . '2026_01_01_000001_create_users.php';

        unlink($first);

        self::assertSame(
            ['2026_01_01_000001_create_users'],
            $this->migrator->missing()
        );

        self::assertSame(
            ['2026_01_01_000001_create_users'],
            $this->migrator->repair()
        );

        self::assertSame([], $this->migrator->missing());
    }

    public function test_pretend_collects_sql_without_changing_database(): void
    {
        $migrator = (new Migrator(
            $this->pdo,
            $this->path,
            $this->repository
        ))->pretend();

        self::assertCount(2, $migrator->migrate());

        $sql = implode("\n", $migrator->getPretendedSql());

        self::assertStringContainsString('CREATE TABLE users', $sql);
        self::assertStringContainsString(
            'ALTER TABLE users ADD COLUMN active',
            $sql
        );

        self::assertSame([], $this->repository->getRan());

        $exists = $this->pdo
            ->query(
                "SELECT 1 FROM sqlite_schema
                 WHERE type = 'table' AND name = 'users'"
            )
            ->fetchColumn();

        self::assertFalse($exists);
    }

    protected function writeMigrations(): void
    {
        $this->write(
            '2026_01_01_000001_create_users',
            <<<'PHP'
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return $this->create('users')
            ->id()
            ->column('name VARCHAR(120) NOT NULL')
            ->column('email VARCHAR(180) NOT NULL UNIQUE')
            ->timestamps();
    }

    public function down(): string|array|SqlStatement
    {
        return $this->drop('users')->ifExists();
    }
};
PHP
        );

        $this->write(
            '2026_01_01_000002_add_active_to_users',
            <<<'PHP'
<?php

use Eril\Migraw\Migration;
use Eril\Migraw\Sql\SqlStatement;

return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return $this->raw(
            'ALTER TABLE users ADD COLUMN active INTEGER NOT NULL DEFAULT 1'
        );
    }

    public function down(): string|array|SqlStatement
    {
        return $this->raw(
            'ALTER TABLE users DROP COLUMN active'
        );
    }
};
PHP
        );
    }

    protected function write(string $name, string $contents): void
    {
        file_put_contents(
            $this->path . DIRECTORY_SEPARATOR . $name . '.php',
            trim($contents) . PHP_EOL
        );
    }
}
