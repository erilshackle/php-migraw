<?php

namespace Eril\Migraw\Tests;

use Eril\Migraw\Migration;
use Eril\Migraw\MigrationRepository;
use Eril\Migraw\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

class MigratorTest extends TestCase
{
    protected string $path;

    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/sql_migrator_tests_' . uniqid();

        mkdir($this->path, 0775, true);

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . '/*.php') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->path)) {
            rmdir($this->path);
        }
    }

    public function test_it_runs_pending_migrations(): void
    {
        $this->createMigration('2026_06_08_000000_create_users_table.php', <<<PHP
        <?php

        use Eril\Migraw\Migration;

        return new class extends Migration {
            public function up(): string|array
            {
                return "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)";
            }

            public function down(): string|array
            {
                return "DROP TABLE users";
            }
        };
        PHP);

        $migrator = $this->makeMigrator();

        $executed = $migrator->migrate();

        $this->assertSame(
            ['2026_06_08_000000_create_users_table'],
            $executed
        );

        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");

        $this->assertSame('users', $stmt->fetchColumn());
    }

    public function test_it_rolls_back_last_batch(): void
    {
        $this->createMigration('2026_06_08_000000_create_users_table.php', <<<PHP
        <?php

        use Eril\Migraw\Migration;

        return new class extends Migration {
            public function up(): string|array
            {
                return "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)";
            }

            public function down(): string|array
            {
                return "DROP TABLE users";
            }
        };
        PHP);

        $migrator = $this->makeMigrator();

        $migrator->migrate();

        $rolledBack = $migrator->rollback();

        $this->assertSame(
            ['2026_06_08_000000_create_users_table'],
            $rolledBack
        );

        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");

        $this->assertFalse($stmt->fetchColumn());
    }

    public function test_it_reports_status(): void
    {
        $this->createMigration('2026_06_08_000000_create_users_table.php', <<<PHP
        <?php

        use Eril\Migraw\Migration;

        return new class extends Migration {
            public function up(): string|array
            {
                return "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT)";
            }

            public function down(): string|array
            {
                return "DROP TABLE users";
            }
        };
        PHP);

        $migrator = $this->makeMigrator();

        $this->assertSame([
            [
                'migration' => '2026_06_08_000000_create_users_table',
                'status' => 'pending',
            ],
        ], $migrator->status());

        $migrator->migrate();

        $this->assertSame([
            [
                'migration' => '2026_06_08_000000_create_users_table',
                'status' => 'ran',
            ],
        ], $migrator->status());
    }

    protected function makeMigrator(): Migrator
    {
        return new Migrator(
            $this->pdo,
            $this->path,
            new MigrationRepository($this->pdo)
        );
    }

    protected function createMigration(string $filename, string $content): void
    {
        file_put_contents($this->path . '/' . $filename, $content);
    }

    public function test_migration_can_return_sql_statement_directly(): void
    {
        $this->createMigration('2026_06_08_000000_create_users_table.php', <<<'PHP'
    <?php

    use Eril\Migraw\Migration;
    use Eril\Migraw\Sql\Sql;
    use Eril\Migraw\Sql\SqlStatement;

    return new class extends Migration {
        public function up(): string|array|SqlStatement
        {
            return Sql::create('users')
                ->field('id INTEGER PRIMARY KEY AUTOINCREMENT')
                ->field('name TEXT NOT NULL');
        }

        public function down(): string|array|SqlStatement
        {
            return Sql::drop('users');
        }
    };
    PHP);

        $migrator = $this->makeMigrator();

        $executed = $migrator->migrate();

        $this->assertSame(
            ['2026_06_08_000000_create_users_table'],
            $executed
        );
    }
}
