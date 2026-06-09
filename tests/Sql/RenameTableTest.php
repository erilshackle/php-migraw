<?php

namespace Eril\SqlMigrator\Tests\Sql;

use Eril\SqlMigrator\Sql\Sql;
use PHPUnit\Framework\TestCase;

class RenameTableTest extends TestCase
{
    public function test_it_generates_rename_table_sql(): void
    {
        $sql = Sql::rename('users')
            ->to('members');

        $this->assertSame(
            'RENAME TABLE users TO members;',
            $sql->toSql()
        );
    }

    public function test_rename_table_requires_new_name(): void
    {
        $this->expectException(\RuntimeException::class);

        Sql::rename('users')->toSql();
    }
}
