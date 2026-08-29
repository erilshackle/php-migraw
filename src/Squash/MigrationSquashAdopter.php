<?php

namespace Eril\Migraw\Squash;

use Eril\Migraw\Migration;
use Eril\Migraw\MigrationRepository;
use PDO;
use RuntimeException;

final class MigrationSquashAdopter
{
    public function __construct(
        protected string $path,
        protected MigrationRepository $repository
    ) {}

    /**
     * Reconcile an existing pre-squash database with committed
     * squash checkpoints.
     *
     * @param callable(string|array|\Eril\Migraw\Sql\SqlStatement): void $execute
     * @return array{caught_up:string[],adopted:string[],assumed_ran:string[]}
     */
    public function reconcile(
        callable $execute,
        bool $pretend = false
    ): array {
        $manifests = $this->manifests();
        $history = $this->repository->getHistory();

        if ($history === [] || $manifests === []) {
            return [
                'caught_up' => [],
                'adopted' => [],
                'assumed_ran' => [],
            ];
        }

        $current = array_values($history);
        $caughtUp = [];
        $adopted = [];
        $assumedRan = [];

        /*
         * If this database already adopted an older squash, start after it.
         */
        $start = 0;

        foreach ($manifests as $index => $item) {
            $baseline = $item['manifest']['baseline']['migration'] ?? null;

            if ($baseline && $this->contains($current, $baseline)) {
                $start = $index + 1;
            }
        }

        for ($i = $start, $count = count($manifests); $i < $count; $i++) {
            $item = $manifests[$i];
            $manifest = $item['manifest'];

            if (($manifest['status'] ?? null) !== 'completed') {
                continue;
            }

            $expected = array_values($manifest['repository'] ?? []);

            if ($expected === []) {
                continue;
            }

            $this->validateHistoryPrefix($current, $expected);

            $missing = array_slice($expected, count($current));

            if ($missing !== []) {
                $batch = $pretend
                    ? $this->nextVirtualBatch($current)
                    : $this->repository->getNextBatchNumber();

                foreach ($missing as $row) {
                    $migrationName = $row['migration'];

                    $file = $this->resolveMigrationFile(
                        $migrationName,
                        $item,
                        $manifests,
                        $i
                    );

                    $this->validateFileChecksum(
                        $file,
                        $row['checksum'] ?? null,
                        $migrationName
                    );

                    $migration = $this->loadMigration($file);

                    $execute($migration->up());

                    if (! $pretend) {
                        $this->repository->log(
                            $migrationName,
                            $batch,
                            $row['checksum'] ?? null
                        );
                    }

                    $current[] = [
                        'migration' => $migrationName,
                        'batch' => $batch,
                        'checksum' => $row['checksum'] ?? null,
                        'executed_at' => null,
                    ];

                    $caughtUp[] = $migrationName;
                }
            }

            /*
             * The complete pre-squash history must now match.
             */
            $this->validateHistoryPrefix($current, $expected, exact: true);

            $baseline = $manifest['baseline'];
            $preserved = [];

            foreach ($manifest['populators'] ?? [] as $entry) {
                if (empty($entry['executed']) || empty($entry['renamed_to'])) {
                    continue;
                }

                $oldName = $entry['migration'];

                if (! $this->contains($current, $oldName)) {
                    continue;
                }

                $file = $this->resolvePopulationFile(
                    $entry,
                    $manifests,
                    $i
                );

                $checksum = hash_file('sha256', $file);

                if (! is_string($checksum)) {
                    throw new RuntimeException(
                        "Unable to calculate population migration checksum: {$file}"
                    );
                }

                $expectedChecksum = $this->checksumFromHistory(
                    $expected,
                    $oldName
                );

                if (
                    $expectedChecksum !== null
                    && $checksum !== $expectedChecksum
                ) {
                    throw new RuntimeException(
                        "Cannot adopt squash baseline: population migration "
                            . "[{$oldName}] has changed."
                    );
                }

                $preserved[$oldName] = [
                    'migration' => $entry['renamed_to'],
                    'checksum' => $checksum,
                ];
            }

            if (! $pretend) {
                $this->repository->replaceWithBaseline(
                    $baseline['migration'],
                    $baseline['checksum'],
                    $preserved
                );
            }

            $current = [
                [
                    'migration' => $baseline['migration'],
                    'batch' => 1,
                    'checksum' => $baseline['checksum'],
                    'executed_at' => null,
                ],
            ];

            foreach ($preserved as $population) {
                $current[] = [
                    'migration' => $population['migration'],
                    'batch' => 2,
                    'checksum' => $population['checksum'],
                    'executed_at' => null,
                ];
            }

            if ($pretend) {
                $assumedRan = array_column(
                    $current,
                    'migration'
                );
            }

            $adopted[] = $baseline['migration'];
        }

        return [
            'caught_up' => $caughtUp,
            'adopted' => $adopted,
            'assumed_ran' => $assumedRan,
        ];
    }

    /**
     * @return array<int,array{archive:string,manifest:array}>
     */
    protected function manifests(): array
    {
        $root = rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'archive';

        if (! is_dir($root)) {
            return [];
        }

        $files = glob(
            $root
                . DIRECTORY_SEPARATOR
                . '*'
                . DIRECTORY_SEPARATOR
                . 'manifest.json'
        ) ?: [];

        $manifests = [];

        foreach ($files as $file) {
            $json = file_get_contents($file);

            if ($json === false) {
                continue;
            }

            $manifest = json_decode($json, true);

            if (! is_array($manifest)) {
                continue;
            }

            if (($manifest['status'] ?? null) !== 'completed') {
                continue;
            }

            $manifests[] = [
                'archive' => dirname($file),
                'manifest' => $manifest,
            ];
        }

        usort(
            $manifests,
            static fn(array $a, array $b): int =>
            strcmp(
                $a['manifest']['created_at'] ?? '',
                $b['manifest']['created_at'] ?? ''
            )
        );

        return $manifests;
    }

    protected function validateHistoryPrefix(
        array $current,
        array $expected,
        bool $exact = false
    ): void {
        if (count($current) > count($expected)) {
            $this->diverged($current, $expected);
        }

        foreach ($current as $index => $row) {
            $expectedRow = $expected[$index] ?? null;

            if (
                $expectedRow === null
                || $row['migration'] !== $expectedRow['migration']
            ) {
                $this->diverged($current, $expected);
            }

            $expectedChecksum = $expectedRow['checksum'] ?? null;
            $currentChecksum = $row['checksum'] ?? null;

            if ($expectedChecksum !== null) {
                if ($currentChecksum === null) {
                    throw new RuntimeException(
                        "Cannot adopt squash baseline: checksum missing for "
                            . "[{$row['migration']}]."
                    );
                }

                if ($currentChecksum !== $expectedChecksum) {
                    throw new RuntimeException(
                        "Cannot adopt squash baseline: migration "
                            . "[{$row['migration']}] has a different checksum."
                    );
                }
            }
        }

        if ($exact && count($current) !== count($expected)) {
            $this->diverged($current, $expected);
        }
    }

    protected function diverged(array $current, array $expected): never
    {
        $currentNames = array_column($current, 'migration');
        $expectedNames = array_column($expected, 'migration');

        $unexpected = array_values(
            array_diff($currentNames, $expectedNames)
        );

        $message = 'Cannot adopt squash baseline: migration history has diverged.';

        if ($unexpected !== []) {
            $message .= "\nUnexpected migrations:\n  - "
                . implode("\n  - ", $unexpected);
        }

        throw new RuntimeException($message);
    }

    protected function resolveMigrationFile(
        string $migration,
        array $item,
        array $manifests,
        int $manifestIndex
    ): string {
        foreach ($item['manifest']['schema'] ?? [] as $entry) {
            if (($entry['migration'] ?? null) !== $migration) {
                continue;
            }

            $file = $item['archive']
                . DIRECTORY_SEPARATOR
                . $entry['file'];

            if (! file_exists($file)) {
                throw new RuntimeException(
                    "Archived migration not found: {$file}"
                );
            }

            return $file;
        }

        foreach ($item['manifest']['populators'] ?? [] as $entry) {
            if (($entry['migration'] ?? null) !== $migration) {
                continue;
            }

            return $this->resolvePopulationFile(
                $entry,
                $manifests,
                $manifestIndex
            );
        }

        throw new RuntimeException(
            "Migration [{$migration}] is missing from squash archive."
        );
    }

    protected function resolvePopulationFile(
        array $entry,
        array $manifests,
        int $manifestIndex
    ): string {
        $name = $entry['renamed_to'] ?? null;
        $file = $entry['renamed_file'] ?? null;

        if (! $name || ! $file) {
            throw new RuntimeException(
                'Invalid population migration mapping in squash manifest.'
            );
        }

        /*
         * Follow retimestamps produced by later squashes.
         */
        for ($i = $manifestIndex + 1, $count = count($manifests); $i < $count; $i++) {
            foreach ($manifests[$i]['manifest']['populators'] ?? [] as $next) {
                if (($next['migration'] ?? null) !== $name) {
                    continue;
                }

                $name = $next['renamed_to'] ?? $name;
                $file = $next['renamed_file'] ?? $file;
                break;
            }
        }

        $path = rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $file;

        if (! file_exists($path)) {
            throw new RuntimeException(
                "Population migration not found: {$path}"
            );
        }

        return $path;
    }

    protected function validateFileChecksum(
        string $file,
        ?string $expected,
        string $migration
    ): void {
        if ($expected === null) {
            return;
        }

        $checksum = hash_file('sha256', $file);

        if (! is_string($checksum) || $checksum !== $expected) {
            throw new RuntimeException(
                "Cannot adopt squash baseline: archived migration "
                    . "[{$migration}] has a different checksum."
            );
        }
    }

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

    protected function contains(array $history, string $migration): bool
    {
        foreach ($history as $row) {
            if (($row['migration'] ?? null) === $migration) {
                return true;
            }
        }

        return false;
    }

    protected function checksumFromHistory(
        array $history,
        string $migration
    ): ?string {
        foreach ($history as $row) {
            if (($row['migration'] ?? null) === $migration) {
                return $row['checksum'] ?? null;
            }
        }

        return null;
    }

    protected function nextVirtualBatch(array $history): int
    {
        $max = 0;

        foreach ($history as $row) {
            $max = max($max, (int) ($row['batch'] ?? 0));
        }

        return $max + 1;
    }
}
