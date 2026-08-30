<?php

namespace Tests\Integration;

use Eril\Migraw\Migration\MigrationRepository;
use Eril\Migraw\Migration\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

final class PopulationTest extends TestCase
{
    protected string $path;
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'migraw_population_'
            . bin2hex(random_bytes(5));

        mkdir($this->path, 0775, true);

        $this->pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->writeMigrations();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->path);
    }

    public function test_population_is_idempotent_and_can_update_selected_columns(): void
    {
        $repository = new MigrationRepository($this->pdo);
        $migrator = new Migrator($this->pdo, $this->path, $repository);

        self::assertCount(2, $migrator->migrate());

        self::assertSame(
            2,
            (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn()
        );

        self::assertSame(
            'Admin',
            $this->pdo->query(
                "SELECT name FROM roles WHERE slug = 'admin'"
            )->fetchColumn()
        );

        $this->writeUpdateMigration();

        self::assertSame(
            ['2026_01_01_000003_update_roles'],
            $migrator->migrate()
        );

        self::assertSame(
            'Administrator',
            $this->pdo->query(
                "SELECT name FROM roles WHERE slug = 'admin'"
            )->fetchColumn()
        );

        self::assertSame(
            ['2026_01_01_000003_update_roles'],
            $migrator->rollback()
        );

        // Populator rollback does not remove populated data.
        self::assertSame(
            2,
            (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn()
        );

        self::assertSame(
            'Administrator',
            $this->pdo->query(
                "SELECT name FROM roles WHERE slug = 'admin'"
            )->fetchColumn()
        );

        // Re-running must remain idempotent.
        self::assertSame(
            ['2026_01_01_000003_update_roles'],
            $migrator->migrate()
        );

        self::assertSame(
            2,
            (int) $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn()
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
        return $this->create('roles')
            ->id()
            ->column('slug VARCHAR(100) NOT NULL UNIQUE')
            ->column('name VARCHAR(120) NOT NULL');
    }

    public function down(): string|array|SqlStatement
    {
        return $this->drop('roles')->ifExists();
    }
};
PHP
        );

        $this->write(
            '2026_01_01_000002_populate_roles',
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
                ['slug' => 'admin', 'name' => 'Admin'],
                ['slug' => 'user', 'name' => 'User'],
            ],
            uniqueBy: 'slug'
        );
    }
};
PHP
        );
    }

    protected function writeUpdateMigration(): void
    {
        $this->write(
            '2026_01_01_000003_update_roles',
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
            uniqueBy: 'slug',
            updateColumns: ['name']
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
