<?php

namespace Tests\Unit;

use Eril\Migraw\Migration\MigrationCreator;
use PHPUnit\Framework\TestCase;

final class MigrationCreatorTest extends TestCase
{
    protected string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'migraw_creator_'
            . bin2hex(random_bytes(5));

        mkdir($this->path, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->path . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->path);
    }

    public function test_populate_prefix_selects_population_template(): void
    {
        $file = (new MigrationCreator(
            $this->path,
            template: 'fluent'
        ))->create('POPULATE_roles');

        $contents = file_get_contents($file);

        self::assertIsString($contents);
        self::assertStringContainsString(
            'extends PopulatorMigration',
            $contents
        );
        self::assertStringNotContainsString('extends Migration', $contents);
    }

    public function test_populate_option_selects_template_without_prefix(): void
    {
        $file = (new MigrationCreator($this->path))->create(
            'initial_roles',
            populate: true
        );

        $contents = file_get_contents($file);

        self::assertIsString($contents);
        self::assertStringContainsString(
            'extends PopulatorMigration',
            $contents
        );
    }

    public function test_sql_option_overrides_population_name_prefix(): void
    {
        $file = (new MigrationCreator($this->path))->create(
            'populate_roles',
            sql: true
        );

        $contents = file_get_contents($file);

        self::assertIsString($contents);
        self::assertStringContainsString('extends Migration', $contents);
        self::assertStringNotContainsString(
            'extends PopulatorMigration',
            $contents
        );
        self::assertStringContainsString(
            '-- Write your UP SQL here',
            $contents
        );
    }
}
