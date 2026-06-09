<?php

namespace Eril\SqlMigrator\Tests\Sql;

use Eril\SqlMigrator\Sql\Sql;
use PHPUnit\Framework\TestCase;

class DropTableTest extends TestCase
{
    public function test_it_generates_drop_table_sql(): void
    {
        $sql = Sql::drop('users');

        $this->assertSame(
            'DROP TABLE users;',
            $sql->toSql()
        );
    }

    public function test_it_generates_drop_table_if_exists_sql(): void
    {
        $sql = Sql::drop('users')->ifExists();

        $this->assertSame(
            'DROP TABLE IF EXISTS users;',
            $sql->toSql()
        );
    }
}