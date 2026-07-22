<?php

namespace Eril\Migraw\Tests;

use Eril\Migraw\MigrationCreator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class MigrationCreatorTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir()
            . '/migraw_creator_tests_'
            . uniqid('', true);

        mkdir($this->path, 0775, true);
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

    public function test_it_creates_a_normal_migration(): void
    {
        $creator = new MigrationCreator(
            $this->path,
            'mysql'
        );

        $file = $creator->create(
            'create_users_table'
        );

        $contents = $this->contents($file);

        $this->assertStringContainsString(
            'use Eril\\Migraw\\Migration;',
            $contents
        );

        $this->assertStringContainsString(
            'extends Migration',
            $contents
        );

        $this->assertStringNotContainsString(
            'PopulatorMigration',
            $contents
        );
    }

    public function test_it_creates_a_populator_migration(): void
    {
        $creator = new MigrationCreator(
            $this->path,
            'mysql'
        );

        $file = $creator->create(
            name: 'populate_roles_table',
            populate: true
        );

        $contents = $this->contents($file);

        $this->assertStringContainsString(
            'use Eril\\Migraw\\PopulatorMigration;',
            $contents
        );

        $this->assertStringContainsString(
            'extends PopulatorMigration',
            $contents
        );

        $this->assertStringContainsString(
            'return $this->populate(',
            $contents
        );

        $this->assertStringContainsString(
            "table: ''",
            $contents
        );

        $this->assertStringContainsString(
            "uniqueBy: 'id'",
            $contents
        );

        $this->assertStringNotContainsString(
            'function down()',
            $contents
        );
    }

    public function test_populator_preserves_the_given_name(): void
    {
        $creator = new MigrationCreator(
            $this->path,
            'mysql'
        );

        $file = $creator->create(
            name: 'roles_table_populator',
            populate: true
        );

        $this->assertMatchesRegularExpression(
            '/^\d{4}_\d{2}_\d{2}_\d{6}_roles_table_populator\.php$/',
            basename($file)
        );
    }

    public function test_blank_and_populate_cannot_be_combined(): void
    {
        $creator = new MigrationCreator(
            $this->path,
            'mysql'
        );

        $this->expectException(
            RuntimeException::class
        );

        $creator->create(
            name: 'populate_roles_table',
            blank: true,
            populate: true
        );
    }

    public function test_it_creates_a_blank_migration(): void
    {
        $creator = new MigrationCreator(
            $this->path,
            'mysql'
        );

        $file = $creator->create(
            name: 'custom_operation',
            blank: true
        );

        $contents = $this->contents($file);

        $this->assertStringContainsString(
            'extends Migration',
            $contents
        );

        $this->assertStringNotContainsString(
            'PopulatorMigration',
            $contents
        );
    }

    protected function contents(string $file): string
    {
        $contents = file_get_contents($file);

        $this->assertNotFalse(
            $contents,
            "Unable to read generated migration: {$file}"
        );

        return $contents;
    }
}