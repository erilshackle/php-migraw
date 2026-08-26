<?php

namespace Eril\Migraw\Squash;

use DateInterval;
use DateTimeImmutable;
use Eril\Migraw\Migration;
use Eril\Migraw\MigrationRepository;
use Eril\Migraw\PopulatorMigration;
use Eril\Migraw\Squash\Dumper\SchemaDriverDumper;
use Eril\Migraw\Squash\Dumper\SchemaDumper;
use RuntimeException;
use Throwable;

/**
 * Replaces executed schema migrations with a schema baseline while preserving
 * population migrations as independent, executable migrations.
 */
final class MigrationSquasher
{

    use Manifest;

    public function __construct(
        protected string $path,
        protected SchemaDumper $dumper,
        protected MigrationRepository $repository
    ) {}

    /**
     * Create a schema baseline, archive old schema migrations and preserve
     * PopulatorMigration files after the generated baseline.
     *
     * Population migrations are retimestamped so a fresh installation always
     * creates the schema before attempting to populate it. Their ran/pending
     * state is preserved in the migration repository.
     *
     * @return array{
     *     migration:string,
     *     file:string,
     *     manifest:string,
     *     archive:string,
     *     tables:string[],
     *     archived:string[],
     *     populators:array<string,string>
     * }
     */
    public function squash(string $name = 'schema'): array
    {
        $name = $this->normalizeName($name);
        $files = $this->migrationFiles();

        if ($files === []) {
            throw new RuntimeException('No migration files found to squash.');
        }

        [$schemaFiles, $populationFiles] = $this->classifyMigrations($files);

        $ran = $this->repository->getRan();
        $history = $this->repository->getHistory();

        $pendingSchema = array_values(array_diff(array_keys($schemaFiles), $ran));

        if ($pendingSchema !== []) {
            throw new RuntimeException(
                "Cannot squash while schema migrations are pending:\n  - "
                    . implode("\n  - ", $pendingSchema)
            );
        }

        $schema = $this->dumper->dump();

        if ($schema === []) {
            throw new RuntimeException('No application tables found to squash.');
        }

        $baselineTime = new DateTimeImmutable();
        $migration = $baselineTime->format('Y_m_d_His') . '_' . $name;
        $file = $this->pathFor($migration);

        if (file_exists($file)) {
            throw new RuntimeException("Migration already exists: {$file}");
        }

        $populationRenames = $this->planPopulationRenames(
            $populationFiles,
            $baselineTime
        );

        if (file_put_contents($file, $this->buildMigration($schema)) === false) {
            throw new RuntimeException("Unable to create squash migration: {$file}");
        }

        $checksum = hash_file('sha256', $file);

        if (! is_string($checksum)) {
            @unlink($file);
            throw new RuntimeException('Unable to calculate squash migration checksum.');
        }

        $archive = $this->archivePath();
        $archived = [];
        $renamedPopulators = [];
        $repositoryReplaced = false;

        $manifest = $this->buildManifest(
            migration: $migration,
            file: $file,
            checksum: $checksum,
            schemaFiles: $schemaFiles,
            populationFiles: $populationFiles,
            populationRenames: $populationRenames,
            history: $history
        );

        try {
            $manifestFile = $this->writeManifest($archive, $manifest);

            foreach ($schemaFiles as $migrationName => $oldFile) {
                $destination = $archive . DIRECTORY_SEPARATOR . basename($oldFile);

                if (! rename($oldFile, $destination)) {
                    throw new RuntimeException(
                        "Unable to archive schema migration: {$oldFile}"
                    );
                }

                $archived[$migrationName] = $destination;
            }

            foreach ($populationRenames as $oldName => $rename) {
                if (! rename($rename['from'], $rename['to'])) {
                    throw new RuntimeException(
                        "Unable to retimestamp population migration: {$rename['from']}"
                    );
                }

                $renamedPopulators[$oldName] = $rename;
            }

            $preservedRan = [];

            foreach ($renamedPopulators as $oldName => $rename) {
                if (! in_array($oldName, $ran, true)) {
                    continue;
                }

                $populationChecksum = hash_file('sha256', $rename['to']);

                if (! is_string($populationChecksum)) {
                    throw new RuntimeException(
                        "Unable to calculate population migration checksum: {$rename['to']}"
                    );
                }

                $preservedRan[$oldName] = [
                    'migration' => $rename['name'],
                    'checksum' => $populationChecksum,
                ];
            }

            $this->repository->replaceWithBaseline(
                $migration,
                $checksum,
                $preservedRan
            );

            $repositoryReplaced = true;

            $manifest['status'] = 'completed';
            $manifest['completed_at'] = date(DATE_ATOM);

            $this->writeManifest($archive, $manifest);
        } catch (Throwable $e) {
            if ($repositoryReplaced) {
                try {
                    $this->repository->restoreHistory($history);
                } catch (Throwable) {
                    // Preserve original exception.
                }
            }

            foreach (array_reverse($renamedPopulators, true) as $rename) {
                if (file_exists($rename['to'])) {
                    @rename($rename['to'], $rename['from']);
                }
            }

            foreach ($archived as $migrationName => $archivedFile) {
                $original = $schemaFiles[$migrationName] ?? null;

                if ($original !== null && file_exists($archivedFile)) {
                    @rename($archivedFile, $original);
                }
            }

            @unlink($file);
            @unlink($archive . DIRECTORY_SEPARATOR . 'manifest.json');
            @unlink($archive . DIRECTORY_SEPARATOR . 'manifest.json.tmp');

            $this->removeDirectoryIfEmpty($archive);

            throw $e;
        }

        return [
            'migration' => $migration,
            'file' => $file,
            'archive' => $archive,
            'manifest' => $manifestFile,
            'tables' => array_keys($schema),
            'archived' => array_keys($archived),
            'populators' => array_map(
                static fn(array $rename): string => $rename['name'],
                $renamedPopulators
            ),
        ];
    }

    /**
     * @return array<string,string>
     */
    protected function migrationFiles(): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $files = glob(
            rtrim($this->path, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . '*.php'
        ) ?: [];

        sort($files);

        $mapped = [];

        foreach ($files as $file) {
            $mapped[basename($file, '.php')] = $file;
        }

        return $mapped;
    }

    /**
     * Split schema migrations from population migrations.
     *
     * @param array<string,string> $files
     * @return array{0:array<string,string>,1:array<string,string>}
     */
    protected function classifyMigrations(array $files): array
    {
        $schema = [];
        $population = [];

        foreach ($files as $name => $file) {
            $migration = $this->loadMigration($file);

            if ($migration instanceof PopulatorMigration) {
                $population[$name] = $file;
                continue;
            }

            $schema[$name] = $file;
        }

        return [$schema, $population];
    }

    /**
     * Load and validate a migration file.
     */
    protected function loadMigration(string $file): Migration
    {
        $migration = require $file;

        if (! $migration instanceof Migration) {
            throw new RuntimeException(
                "Migration file must return an instance of Migration: {$file}"
            );
        }

        return $migration;
    }

    /**
     * Retimestamp population migrations so they sort after the new baseline.
     * Their relative order is preserved.
     *
     * @param array<string,string> $populationFiles
     * @return array<string,array{from:string,to:string,name:string}>
     */
    protected function planPopulationRenames(
        array $populationFiles,
        DateTimeImmutable $baselineTime
    ): array {
        $planned = [];
        $time = $baselineTime;
        $reserved = [];

        foreach ($populationFiles as $oldName => $oldFile) {
            $suffix = $this->migrationSuffix($oldName);

            do {
                $time = $time->add(new DateInterval('PT1S'));
                $newName = $time->format('Y_m_d_His') . '_' . $suffix;
                $newFile = $this->pathFor($newName);
            } while (
                isset($reserved[$newFile])
                || (file_exists($newFile) && $newFile !== $oldFile)
            );

            $reserved[$newFile] = true;

            $planned[$oldName] = [
                'from' => $oldFile,
                'to' => $newFile,
                'name' => $newName,
            ];
        }

        return $planned;
    }

    protected function migrationSuffix(string $migration): string
    {
        if (preg_match(
            '/^\d{4}_\d{2}_\d{2}_\d{6}_(.+)$/',
            $migration,
            $matches
        )) {
            return $matches[1];
        }

        return $this->normalizeName($migration);
    }

    /**
     * Build the squash recovery manifest.
     *
     * @param array<string,string> $schemaFiles
     * @param array<string,string> $populationFiles
     * @param array<string,array{from:string,to:string,name:string}> $populationRenames
     * @param array<int,array<string,mixed>> $history
     *
     * @return array<string,mixed>
     */
    protected function buildManifest(
        string $migration,
        string $file,
        string $checksum,
        array $schemaFiles,
        array $populationFiles,
        array $populationRenames,
        array $history
    ): array {
        $ran = [];

        foreach ($history as $row) {
            $ran[$row['migration']] = true;
        }

        $schema = [];

        foreach ($schemaFiles as $name => $schemaFile) {
            $schema[] = [
                'migration' => $name,
                'file' => basename($schemaFile),
            ];
        }

        $populators = [];

        foreach ($populationFiles as $name => $populationFile) {
            $rename = $populationRenames[$name] ?? null;

            $populators[] = [
                'migration' => $name,
                'file' => basename($populationFile),

                'renamed_to' => $rename['name'] ?? null,
                'renamed_file' => isset($rename['to'])
                    ? basename($rename['to'])
                    : null,

                'executed' => isset($ran[$name]),
            ];
        }

        return [
            'version' => 1,

            /*
            * "prepared" means the manifest was written before filesystem and
            * repository changes were completed.
            */
            'status' => 'prepared',

            'created_at' => date(DATE_ATOM),

            'baseline' => [
                'migration' => $migration,
                'file' => basename($file),
                'checksum' => $checksum,
            ],

            /*
            * Exact repository snapshot before squash.
            */
            'repository' => array_values($history),

            /*
            * Schema migrations moved to archive/.
            */
            'schema' => $schema,

            /*
            * Population migrations remain active but are retimestamped.
            */
            'populators' => $populators,
        ];
    }

    

    /**
     * @param array<string,array<string>> $schema
     */
    protected function buildMigration(array $schema): string
{
    $up = $this->dumper->beforeCreate();

    foreach ($schema as $statements) {
        foreach ($statements as $statement) {
            $up[] = $statement;
        }
    }

    array_push($up, ...$this->dumper->afterCreate());

    $down = $this->dumper->beforeDrop();

    foreach (array_reverse(array_keys($schema)) as $table) {
        $down[] = $this->dumper->dropTable($table);
    }

    array_push($down, ...$this->dumper->afterDrop());

    $upStatements = $this->renderStatements($up);
    $downStatements = $this->renderStatements($down);

    return <<<PHP
<?php

use Eril\\Migraw\\Migration;
use Eril\\Migraw\\Sql\\SqlStatement;

/**
 * Squashed schema baseline generated from the current database state.
 */
return new class extends Migration
{
    public function up(): string|array|SqlStatement
    {
        return [
{$upStatements}
        ];
    }

    public function down(): string|array|SqlStatement
    {
        return [
{$downStatements}
        ];
    }
};

PHP;
}

    /**
     * @param string[] $statements
     */
    protected function renderStatements(array $statements): string
    {
        $blocks = [];

        foreach ($statements as $statement) {
            $statement = trim($statement);

            $blocks[] = <<<PHP
            \$this->raw(<<<'SQL'
{$statement}
SQL)
PHP;
        }

        return implode(",\n\n", $blocks) . ',';
    }

    protected function pathFor(string $migration): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $migration
            . '.php';
    }

    protected function archivePath(): string
    {
        return rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'archive'
            . DIRECTORY_SEPARATOR
            . date('Ymd_His');
    }

    

    protected function removeDirectoryIfEmpty(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = array_diff(scandir($path) ?: [], ['.', '..']);

        if ($items === []) {
            @rmdir($path);
        }
    }

    protected function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '_', $name) ?? '';
        $name = trim($name, '_');

        if ($name === '') {
            throw new RuntimeException('Squash migration name cannot be empty.');
        }

        return $name;
    }

}
