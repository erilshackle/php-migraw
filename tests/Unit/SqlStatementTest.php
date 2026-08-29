<?php

namespace Tests\Unit;

use Eril\Migraw\Sql\Sql;
use PHPUnit\Framework\TestCase;

final class SqlStatementTest extends TestCase
{
    public function test_create_table_compiles_driver_specific_sql(): void
    {
        $statement = Sql::create('users')
            ->id()
            ->column('email VARCHAR(180) NOT NULL')
            ->unique('uq_users_email', 'email')
            ->timestamps()
            ->softDeletes();

        $sqlite = $statement->toSql('sqlite');
        $mysql = $statement->toSql('mysql');
        $pgsql = $statement->toSql('pgsql');

        self::assertStringContainsString(
            'id INTEGER PRIMARY KEY AUTOINCREMENT',
            $sqlite
        );
        self::assertStringContainsString(
            'id INT AUTO_INCREMENT PRIMARY KEY',
            $mysql
        );
        self::assertStringContainsString(
            'id SERIAL PRIMARY KEY',
            $pgsql
        );

        self::assertStringContainsString(
            'CONSTRAINT uq_users_email UNIQUE (email)',
            $sqlite
        );
        self::assertStringContainsString(
            'deleted_at TEXT NULL',
            $sqlite
        );
        self::assertStringContainsString(
            'deleted_at TIMESTAMP NULL',
            $pgsql
        );
    }

    public function test_populate_compiles_for_supported_drivers(): void
    {
        $statement = Sql::populate(
            'roles',
            [
                ['slug' => 'admin', 'name' => 'Administrator'],
            ],
            'slug'
        )->update('name');

        self::assertStringContainsString(
            'ON CONFLICT ("slug") DO UPDATE SET',
            $statement->toSql('sqlite')
        );

        self::assertStringContainsString(
            'ON CONFLICT ("slug") DO UPDATE SET',
            $statement->toSql('pgsql')
        );

        self::assertStringContainsString(
            'ON DUPLICATE KEY UPDATE',
            $statement->toSql('mysql')
        );
    }

    public function test_basic_schema_statements_compile(): void
    {
        self::assertSame(
            'DROP TABLE IF EXISTS users;',
            Sql::drop('users')->ifExists()->toSql('sqlite')
        );

        self::assertSame(
            'RENAME TABLE users TO members;',
            Sql::rename('users')->to('members')->toSql('mysql')
        );

        self::assertStringContainsString(
            'ALTER TABLE users',
            Sql::alter('users')
                ->add('phone VARCHAR(30) NULL')
                ->toSql('sqlite')
        );
    }
}
