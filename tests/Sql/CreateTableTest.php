<?php

namespace Eril\SqlMigrator\Tests\Sql;

use Eril\SqlMigrator\Sql\Sql;
use PHPUnit\Framework\TestCase;

class CreateTableTest extends TestCase
{
    public function test_it_generates_create_table_sql(): void
    {
        $sql = Sql::create('users')
            ->field('id INT AUTO_INCREMENT PRIMARY KEY')
            ->field('name VARCHAR(255) NOT NULL')
            ->field('email VARCHAR(255) UNIQUE');

        $this->assertSame(
            "CREATE TABLE users (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    name VARCHAR(255) NOT NULL,\n    email VARCHAR(255) UNIQUE\n);",
            $sql->toSql()
        );
    }

    public function test_it_generates_create_table_if_not_exists_sql(): void
    {
        $sql = Sql::create('users')
            ->ifNotExists()
            ->field('id INT PRIMARY KEY');

        $this->assertSame(
            "CREATE TABLE IF NOT EXISTS users (\n    id INT PRIMARY KEY\n);",
            $sql->toSql()
        );
    }

    public function test_it_adds_constraints(): void
    {
        $sql = Sql::create('posts')
            ->field('id INT AUTO_INCREMENT PRIMARY KEY')
            ->field('user_id INT NOT NULL')
            ->constraint('FOREIGN KEY (user_id) REFERENCES users(id)');

        $this->assertSame(
            "CREATE TABLE posts (\n    id INT AUTO_INCREMENT PRIMARY KEY,\n    user_id INT NOT NULL,\n    FOREIGN KEY (user_id) REFERENCES users(id)\n);",
            $sql->toSql()
        );
    }

    public function test_create_table_requires_fields_or_constraints(): void
    {
        $this->expectException(\RuntimeException::class);

        Sql::create('users')->toSql();
    }
}
