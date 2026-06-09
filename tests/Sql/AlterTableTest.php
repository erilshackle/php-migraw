<?php

namespace Eril\SqlMigrator\Tests\Sql;

use Eril\SqlMigrator\Sql\Sql;
use PHPUnit\Framework\TestCase;

class AlterTableTest extends TestCase
{
    public function test_it_generates_alter_table_sql(): void
    {
        $sql = Sql::alter('users')
            ->add('phone VARCHAR(50) NULL')
            ->modify('name VARCHAR(180) NOT NULL')
            ->drop('old_column');

        $this->assertSame(
            "ALTER TABLE users\n    ADD COLUMN phone VARCHAR(50) NULL,\n    MODIFY COLUMN name VARCHAR(180) NOT NULL,\n    DROP COLUMN old_column;",
            $sql->toSql()
        );
    }
    public function test_it_generates_rename_column_sql(): void
    {
        $sql = Sql::alter('users')
            ->rename('name', 'full_name');

        $this->assertSame(
            "ALTER TABLE users\n    RENAME COLUMN name TO full_name;",
            $sql->toSql()
        );
    }

    public function test_it_allows_raw_operations(): void
    {
        $sql = Sql::alter('users')
            ->raw('ADD INDEX idx_email (email)');

        $this->assertSame(
            "ALTER TABLE users\n    ADD INDEX idx_email (email);",
            $sql->toSql()
        );
    }
}