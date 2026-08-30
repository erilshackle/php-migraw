<?php

namespace Tests\Squash;

use Eril\Migraw\Schema\SchemaDumper;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\SchemaAssertions;

final class SqliteSchemaDumperTest extends TestCase
{
    public function test_it_dumps_and_recreates_sqlite_schema(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed.');
        }

        $source = $this->sqlite();
        $this->createSourceSchema($source);

        $dumper = new SchemaDumper($source, 'migrations');
        $schema = $dumper->dump();

        self::assertSame(['roles', 'users', 'posts'], array_keys($schema));
        self::assertArrayNotHasKey('migrations', $schema);

        $allSql = implode("\n", array_merge(...array_values($schema)));

        self::assertStringContainsString('CREATE TABLE roles', $allSql);
        self::assertStringContainsString('CREATE TABLE users', $allSql);
        self::assertStringContainsString('CREATE TABLE posts', $allSql);
        self::assertStringContainsString('CREATE INDEX idx_posts_title', $allSql);

        $target = $this->sqlite();

        $this->execute($target, $dumper->beforeCreate());

        foreach ($schema as $statements) {
            $this->execute($target, $statements);
        }

        $this->execute($target, $dumper->afterCreate());

        SchemaAssertions::tableExists($target, 'sqlite', 'roles');
        SchemaAssertions::tableExists($target, 'sqlite', 'users');
        SchemaAssertions::tableExists($target, 'sqlite', 'posts');
        SchemaAssertions::tableMissing($target, 'sqlite', 'migrations');

        $columns = $target->query('PRAGMA table_info("users")')->fetchAll();
        self::assertSame(
            ['id', 'role_id', 'email', 'active', 'created_at'],
            array_column($columns, 'name')
        );

        $indexes = $target->query('PRAGMA index_list("posts")')->fetchAll();
        self::assertContains('idx_posts_title', array_column($indexes, 'name'));

        $foreignKeys = $target->query('PRAGMA foreign_key_list("posts")')->fetchAll();
        self::assertSame('users', $foreignKeys[0]['table'] ?? null);
        self::assertSame('CASCADE', strtoupper($foreignKeys[0]['on_delete'] ?? ''));

        $target->exec("INSERT INTO roles (slug, name) VALUES ('admin', 'Administrator')");
        $target->exec(
            "INSERT INTO users (role_id, email) VALUES (1, 'admin@example.test')"
        );
        $target->exec(
            "INSERT INTO posts (user_id, title) VALUES (1, 'Hello')"
        );

        self::assertSame('1', (string) $target->query('SELECT COUNT(*) FROM posts')->fetchColumn());
    }

    public function test_sqlite_drop_statements_are_executable(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not installed.');
        }

        $pdo = $this->sqlite();
        $this->createSourceSchema($pdo);

        $dumper = new SchemaDumper($pdo, 'migrations');
        $schema = $dumper->dump();

        $this->execute($pdo, $dumper->beforeDrop());

        foreach (array_reverse(array_keys($schema)) as $table) {
            $pdo->exec($dumper->dropTable($table));
        }

        $this->execute($pdo, $dumper->afterDrop());

        SchemaAssertions::tableMissing($pdo, 'sqlite', 'roles');
        SchemaAssertions::tableMissing($pdo, 'sqlite', 'users');
        SchemaAssertions::tableMissing($pdo, 'sqlite', 'posts');
        SchemaAssertions::tableExists($pdo, 'sqlite', 'migrations');
    }

    protected function sqlite(): PDO
    {
        $pdo = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    protected function createSourceSchema(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    migration TEXT NOT NULL UNIQUE,
    batch INTEGER NOT NULL
)
SQL);

        $pdo->exec(<<<'SQL'
CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
)
SQL);

        $pdo->exec(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    role_id INTEGER NOT NULL,
    email TEXT NOT NULL UNIQUE,
    active INTEGER NOT NULL DEFAULT 1 CHECK (active IN (0, 1)),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
)
SQL);

        $pdo->exec(<<<'SQL'
CREATE TABLE posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    body TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL);

        $pdo->exec('CREATE INDEX idx_posts_title ON posts(title)');
    }

    protected function execute(PDO $pdo, array $statements): void
    {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }
}
