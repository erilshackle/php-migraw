<?php

namespace Eril\Migraw\Tests;

use Eril\Migraw\MigrationCreator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MigrationCreatorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'migraw_creator_tests_'
            . bin2hex(random_bytes(8));

        mkdir($this->path, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->path);

        parent::tearDown();
    }

    public function test_it_creates_a_smart_migration_using_raw_statement(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $file = $creator->create('create_users_table');

        $this->assertFileExists($file);

        $this->assertMatchesRegularExpression(
            '/^\d{4}_\d{2}_\d{2}_\d{6}_create_users_table\.php$/',
            basename($file)
        );

        $contents = file_get_contents($file);

        $this->assertIsString($contents);

        $this->assertStringContainsString(
            'return new class extends Migration',
            $contents
        );

        $this->assertStringContainsString(
            'return $this->raw(<<<SQL',
            $contents
        );

        $this->assertStringContainsString(
            'CREATE TABLE users',
            $contents
        );

        $this->assertStringContainsString(
            'DROP TABLE IF EXISTS users;',
            $contents
        );
    }

    public function test_sql_option_creates_a_blank_plain_sql_migration(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $file = $creator->create(
            name: 'custom_operation',
            sql: true
        );

        $contents = file_get_contents($file);

        $this->assertIsString($contents);

        $this->assertStringContainsString(
            '-- Write your UP SQL here',
            $contents
        );

        $this->assertStringContainsString(
            '-- Write your DOWN SQL here',
            $contents
        );

        $this->assertStringContainsString(
            'return <<<SQL',
            $contents
        );

        $this->assertStringNotContainsString(
            '$this->raw(<<<SQL',
            $contents
        );
    }

    public function test_populate_option_creates_a_populator_migration(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $file = $creator->create(
            name: 'populate_default_roles',
            populate: true
        );

        $contents = file_get_contents($file);

        $this->assertIsString($contents);

        $this->assertStringContainsString(
            'return new class extends PopulatorMigration',
            $contents
        );

        $this->assertStringContainsString(
            'public function population(): string|array|SqlStatement',
            $contents
        );

        $this->assertStringContainsString(
            'return $this->populate(',
            $contents
        );

        $this->assertStringNotContainsString(
            'public function down(',
            $contents
        );

        $this->assertStringNotContainsString(
            'public function up(',
            $contents
        );
    }

    public function test_sql_and_populate_options_cannot_be_combined(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'A migration cannot be both SQL and populate.'
        );

        $creator->create(
            name: 'invalid_migration',
            sql: true,
            populate: true
        );
    }

    public function test_it_normalizes_the_migration_name(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $file = $creator->create('Create User Profiles Table');

        $this->assertMatchesRegularExpression(
            '/^\d{4}_\d{2}_\d{2}_\d{6}_create_user_profiles_table\.php$/',
            basename($file)
        );
    }

    public function test_it_rejects_an_empty_migration_name(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Migration name cannot be empty.'
        );

        $creator->create('---');
    }

    public function test_unknown_name_generates_a_blank_raw_statement_migration(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $file = $creator->create('perform_custom_operation');

        $contents = file_get_contents($file);

        $this->assertIsString($contents);

        $this->assertStringContainsString(
            'return $this->raw(<<<SQL',
            $contents
        );

        $this->assertStringContainsString(
            '-- Write your UP SQL here',
            $contents
        );

        $this->assertStringContainsString(
            '-- Write your DOWN SQL here',
            $contents
        );
    }

    public function test_generated_smart_migration_is_valid_php(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $file = $creator->create('create_users_table');

        $output = [];
        $exitCode = 0;

        exec(
            escapeshellarg(PHP_BINARY)
                . ' -l '
                . escapeshellarg($file),
            $output,
            $exitCode
        );

        $this->assertSame(
            0,
            $exitCode,
            implode(PHP_EOL, $output)
        );
    }

    public function test_generated_sql_migration_is_valid_php(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $file = $creator->create(
            name: 'custom_operation',
            sql: true
        );

        $output = [];
        $exitCode = 0;

        exec(
            escapeshellarg(PHP_BINARY)
                . ' -l '
                . escapeshellarg($file),
            $output,
            $exitCode
        );

        $this->assertSame(
            0,
            $exitCode,
            implode(PHP_EOL, $output)
        );
    }

    public function test_generated_populator_migration_is_valid_php(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $file = $creator->create(
            name: 'populate_default_roles',
            populate: true
        );

        $output = [];
        $exitCode = 0;

        exec(
            escapeshellarg(PHP_BINARY)
                . ' -l '
                . escapeshellarg($file),
            $output,
            $exitCode
        );

        $this->assertSame(
            0,
            $exitCode,
            implode(PHP_EOL, $output)
        );
    }

    public function test_mysql_create_template_uses_auto_increment(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'mysql'
        );

        $file = $creator->create('create_users_table');

        $contents = file_get_contents($file);

        $this->assertIsString($contents);

        $this->assertStringContainsString(
            'id INT AUTO_INCREMENT PRIMARY KEY',
            $contents
        );

        $this->assertStringContainsString(
            'ON UPDATE CURRENT_TIMESTAMP',
            $contents
        );
    }

    public function test_postgresql_create_template_uses_serial(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'pgsql'
        );

        $file = $creator->create('create_users_table');

        $contents = file_get_contents($file);

        $this->assertIsString($contents);

        $this->assertStringContainsString(
            'id SERIAL PRIMARY KEY',
            $contents
        );

        $this->assertStringNotContainsString(
            'AUTO_INCREMENT',
            $contents
        );
    }

    public function test_sqlite_create_template_uses_integer_primary_key(): void
    {
        $creator = new MigrationCreator(
            path: $this->path,
            driver: 'sqlite'
        );

        $file = $creator->create('create_users_table');

        $contents = file_get_contents($file);

        $this->assertIsString($contents);

        $this->assertStringContainsString(
            'id INTEGER PRIMARY KEY AUTOINCREMENT',
            $contents
        );

        $this->assertStringContainsString(
            'created_at TEXT DEFAULT CURRENT_TIMESTAMP',
            $contents
        );
    }

    private function deleteDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory
                . DIRECTORY_SEPARATOR
                . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
