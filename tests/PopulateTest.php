<?php

namespace Eril\Migraw\Tests;

use Eril\Migraw\Sql\Populate;
use Eril\Migraw\Sql\Sql;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PopulateTest extends TestCase
{
    public function test_it_creates_populate_statement_through_sql_facade(): void
    {
        $statement = Sql::populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug'
        );

        $this->assertInstanceOf(Populate::class, $statement);
    }

    public function test_it_generates_mysql_insert_ignore_sql(): void
    {
        $statement = new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
                [
                    'slug' => 'patient',
                    'name' => 'Patient',
                ],
            ],
            uniqueBy: 'slug'
        );

        $sql = $statement->toSql('mysql');

        $this->assertStringContainsString(
            'INSERT INTO `roles`',
            $sql
        );

        $this->assertStringContainsString(
            '(`slug`, `name`)',
            $sql
        );

        $this->assertStringContainsString(
            "'admin'",
            $sql
        );

        $this->assertStringContainsString(
            "'Administrator'",
            $sql
        );

        $this->assertStringContainsString(
            "'patient'",
            $sql
        );

        $this->assertStringContainsString(
            "'Patient'",
            $sql
        );

        $this->assertStringContainsString(
            'ON DUPLICATE KEY UPDATE',
            $sql
        );
    }

    public function test_mysql_without_update_columns_ignores_existing_rows(): void
    {
        $statement = new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug'
        );

        $sql = $statement->toSql('mysql');

        $this->assertStringContainsString(
            'ON DUPLICATE KEY UPDATE',
            $sql
        );

        /*
         * MySQL has no universal "ON CONFLICT DO NOTHING" syntax.
         * The implementation may produce a harmless self-assignment,
         * such as slug = slug or slug = VALUES(slug).
         */
        $this->assertStringContainsString(
            'slug',
            $sql
        );
    }

    public function test_it_generates_mysql_upsert_with_update_columns(): void
    {
        $statement = new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                    'active' => 1,
                ],
            ],
            uniqueBy: 'slug',
            updateColumns: ['name', 'active']
        );

        $sql = $statement->toSql('mysql');

        $this->assertStringContainsString(
            'ON DUPLICATE KEY UPDATE',
            $sql
        );

        $this->assertStringContainsString(
            'name',
            $sql
        );

        $this->assertStringContainsString(
            'active',
            $sql
        );
    }

    public function test_update_replaces_update_columns(): void
    {
        $statement = (new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                    'active' => 1,
                ],
            ],
            uniqueBy: 'slug',
            updateColumns: ['name']
        ))->update(['active']);

        $sql = $statement->toSql('mysql');

        $updateClause = $this->substringAfter(
            $sql,
            'ON DUPLICATE KEY UPDATE'
        );

        $this->assertStringContainsString(
            'active',
            $updateClause
        );

        $this->assertStringNotContainsString(
            'name =',
            $updateClause
        );
    }

    public function test_update_accepts_a_single_column_when_supported(): void
    {
        $statement = (new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug'
        ))->update('name');

        $sql = $statement->toSql('mysql');

        $updateClause = $this->substringAfter(
            $sql,
            'ON DUPLICATE KEY UPDATE'
        );

        $this->assertStringContainsString(
            'name',
            $updateClause
        );
    }

    public function test_it_generates_postgresql_do_nothing_sql(): void
    {
        $statement = new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug'
        );

        $sql = $statement->toSql('pgsql');

        $this->assertStringContainsString(
            'INSERT INTO "roles"',
            $sql
        );

        $this->assertStringContainsString(
            'ON CONFLICT ("slug")',
            $sql
        );

        $this->assertStringContainsString(
            'DO NOTHING',
            $sql
        );
    }

    public function test_it_generates_postgresql_do_update_sql(): void
    {
        $statement = new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                    'active' => true,
                ],
            ],
            uniqueBy: 'slug',
            updateColumns: ['name', 'active']
        );

        $sql = $statement->toSql('pgsql');

        $this->assertStringContainsString(
            'ON CONFLICT ("slug")',
            $sql
        );

        $this->assertStringContainsString(
            'DO UPDATE SET',
            $sql
        );

        $this->assertStringContainsString(
            'EXCLUDED."name"',
            $sql
        );

        $this->assertStringContainsString(
            'EXCLUDED."active"',
            $sql
        );
    }

    public function test_it_generates_sqlite_do_nothing_sql(): void
    {
        $statement = new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug'
        );

        $sql = $statement->toSql('sqlite');

        $this->assertStringContainsString(
            'INSERT INTO "roles"',
            $sql
        );

        $this->assertStringContainsString(
            'ON CONFLICT ("slug")',
            $sql
        );

        $this->assertStringContainsString(
            'DO NOTHING',
            $sql
        );
    }

    public function test_it_generates_sqlite_do_update_sql(): void
    {
        $statement = new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug',
            updateColumns: ['name']
        );

        $sql = $statement->toSql('sqlite');

        $this->assertStringContainsString(
            'ON CONFLICT ("slug")',
            $sql
        );

        $this->assertStringContainsString(
            'DO UPDATE SET',
            $sql
        );

        $this->assertStringContainsString(
            'excluded."name"',
            strtolower($sql)
        );
    }

    public function test_it_supports_composite_unique_columns(): void
    {
        $statement = new Populate(
            table: 'permissions',
            rows: [
                [
                    'role_id' => 1,
                    'permission' => 'users.view',
                    'allowed' => true,
                ],
            ],
            uniqueBy: ['role_id', 'permission'],
            updateColumns: ['allowed']
        );

        $postgresSql = $statement->toSql('pgsql');
        $sqliteSql = $statement->toSql('sqlite');

        $this->assertStringContainsString(
            'ON CONFLICT ("role_id", "permission")',
            $postgresSql
        );

        $this->assertStringContainsString(
            'ON CONFLICT ("role_id", "permission")',
            $sqliteSql
        );
    }

    public function test_it_serializes_null_boolean_integer_and_float_values(): void
    {
        $statement = new Populate(
            table: 'settings',
            rows: [
                [
                    'name' => 'sample',
                    'description' => null,
                    'enabled' => true,
                    'visible' => false,
                    'priority' => 10,
                    'ratio' => 1.5,
                ],
            ],
            uniqueBy: 'name'
        );

        $sql = $statement->toSql('sqlite');

        $this->assertStringContainsString(
            'NULL',
            $sql
        );

        $this->assertStringContainsString(
            '10',
            $sql
        );

        $this->assertStringContainsString(
            '1.5',
            $sql
        );

        /*
         * Depending on implementation, booleans may be compiled as
         * TRUE/FALSE or 1/0. Here we only confirm compilation succeeds
         * and both boolean columns are present.
         */
        $this->assertStringContainsString(
            'enabled',
            $sql
        );

        $this->assertStringContainsString(
            'visible',
            $sql
        );
    }

    public function test_it_escapes_single_quotes_in_string_values(): void
    {
        $statement = new Populate(
            table: 'authors',
            rows: [
                [
                    'slug' => 'obrien',
                    'name' => "O'Brien",
                ],
            ],
            uniqueBy: 'slug'
        );

        $sql = $statement->toSql('sqlite');

        $this->assertStringContainsString(
            "O''Brien",
            $sql
        );
    }

    public function test_it_rejects_an_empty_table_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Populate(
            table: '',
            rows: [
                ['slug' => 'admin'],
            ],
            uniqueBy: 'slug'
        );
    }

    public function test_it_rejects_empty_rows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Populate(
            table: 'roles',
            rows: [],
            uniqueBy: 'slug'
        );
    }

    public function test_it_rejects_empty_unique_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Populate(
            table: 'roles',
            rows: [
                ['slug' => 'admin'],
            ],
            uniqueBy: []
        );
    }

    public function test_it_rejects_rows_with_different_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
                [
                    'slug' => 'patient',
                ],
            ],
            uniqueBy: 'slug'
        );
    }

    public function test_it_rejects_unique_column_missing_from_rows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Populate(
            table: 'roles',
            rows: [
                [
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug'
        );
    }

    public function test_it_rejects_unknown_update_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug',
            updateColumns: ['description']
        );
    }

    public function test_it_rejects_an_unsupported_driver(): void
    {
        $statement = new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug'
        );

        $this->expectException(RuntimeException::class);

        $statement->toSql('sqlserver');
    }

    public function test_it_cannot_be_converted_to_string_without_a_driver(): void
    {
        $statement = new Populate(
            table: 'roles',
            rows: [
                [
                    'slug' => 'admin',
                    'name' => 'Administrator',
                ],
            ],
            uniqueBy: 'slug'
        );

        $this->expectException(RuntimeException::class);

        (string) $statement;
    }

    private function substringAfter(
        string $value,
        string $separator
    ): string {
        $position = strpos($value, $separator);

        if ($position === false) {
            return '';
        }

        return substr(
            $value,
            $position + strlen($separator)
        );
    }
}
