<?php

namespace Eril\Migraw\Squash;

use Eril\Migraw\Migration\MigrationRepository;
use RuntimeException;
use Throwable;

final class MigrationUnsquasher
{

    use Manifest;
    
    public function __construct(
        protected string $path,
        protected MigrationRepository $repository
    ) {}

    public function unsquash(?string $archive = null): array
    {
        $archive = $archive
            ? $this->resolveArchive($archive)
            : $this->latestArchive();

        $manifestFile = $archive . DIRECTORY_SEPARATOR . 'manifest.json';
        $manifest = $this->loadManifest($manifestFile);

        if (($manifest['status'] ?? null) !== 'completed') {
            throw new RuntimeException(
                'Cannot unsquash an incomplete squash operation.'
            );
        }

        $this->validateCurrentState($manifest);

        $baselineFile = rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $manifest['baseline']['file'];

        $currentHistory = $this->repository->getHistory();

        $restoredSchema = [];
        $restoredPopulators = [];
        $baselineRemoved = false;
        $repositoryRestored = false;

        $baselineContents = file_exists($baselineFile)
            ? file_get_contents($baselineFile)
            : null;

        try {
            foreach ($manifest['schema'] ?? [] as $entry) {
                $from = $archive . DIRECTORY_SEPARATOR . $entry['file'];
                $to = $this->path . DIRECTORY_SEPARATOR . $entry['file'];

                if (! file_exists($from)) {
                    throw new RuntimeException(
                        "Archived migration not found: {$from}"
                    );
                }

                if (file_exists($to)) {
                    throw new RuntimeException(
                        "Cannot restore migration; file already exists: {$to}"
                    );
                }

                if (! rename($from, $to)) {
                    throw new RuntimeException(
                        "Unable to restore migration: {$from}"
                    );
                }

                $restoredSchema[] = compact('from', 'to');
            }

            foreach ($manifest['populators'] ?? [] as $entry) {
                $renamed = $entry['renamed_file'] ?? null;
                $original = $entry['file'] ?? null;

                if (! $renamed || ! $original) {
                    continue;
                }

                $from = $this->path . DIRECTORY_SEPARATOR . $renamed;
                $to = $this->path . DIRECTORY_SEPARATOR . $original;

                if (! file_exists($from)) {
                    throw new RuntimeException(
                        "Retimestamped populator not found: {$from}"
                    );
                }

                if (file_exists($to)) {
                    throw new RuntimeException(
                        "Cannot restore populator; file already exists: {$to}"
                    );
                }

                if (! rename($from, $to)) {
                    throw new RuntimeException(
                        "Unable to restore populator: {$from}"
                    );
                }

                $restoredPopulators[] = compact('from', 'to');
            }

            $this->repository->restoreHistory(
                $manifest['repository'] ?? []
            );

            $repositoryRestored = true;

            if (file_exists($baselineFile)) {
                if (! unlink($baselineFile)) {
                    throw new RuntimeException(
                        "Unable to remove squash baseline: {$baselineFile}"
                    );
                }

                $baselineRemoved = true;
            }

            $manifest['status'] = 'unsquashed';
            $manifest['unsquashed_at'] = date(DATE_ATOM);

            // $this->writeManifest($manifestFile, $manifest);
            $this->writeManifest($archive, $manifest);
        } catch (Throwable $e) {
            if ($repositoryRestored) {
                try {
                    $this->repository->restoreHistory($currentHistory);
                } catch (Throwable) {
                    // Preserve original exception.
                }
            }

            foreach (array_reverse($restoredPopulators) as $rename) {
                if (file_exists($rename['to'])) {
                    @rename($rename['to'], $rename['from']);
                }
            }

            foreach (array_reverse($restoredSchema) as $rename) {
                if (file_exists($rename['to'])) {
                    @rename($rename['to'], $rename['from']);
                }
            }

            if ($baselineRemoved && is_string($baselineContents)) {
                @file_put_contents($baselineFile, $baselineContents);
            }

            throw $e;
        }

        return [
            'archive' => $archive,
            'baseline' => $manifest['baseline']['migration'],
            'restored' => count($restoredSchema),
            'populators' => count($restoredPopulators),
        ];
    }

    protected function loadManifest(string $file): array
    {
        if (! file_exists($file)) {
            throw new RuntimeException(
                "Squash manifest not found: {$file}"
            );
        }

        $json = file_get_contents($file);

        if ($json === false) {
            throw new RuntimeException(
                "Unable to read squash manifest: {$file}"
            );
        }

        $manifest = json_decode($json, true);

        if (! is_array($manifest)) {
            throw new RuntimeException(
                "Invalid squash manifest: {$file}"
            );
        }

        return $manifest;
    }

    protected function validateCurrentState(array $manifest): void
    {
        $baseline = $manifest['baseline']['migration'] ?? null;

        if (! $baseline) {
            throw new RuntimeException(
                'Invalid squash manifest: baseline is missing.'
            );
        }

        $ran = $this->repository->getRan();

        if (! in_array($baseline, $ran, true)) {
            throw new RuntimeException(
                "Squash baseline is not currently executed: {$baseline}"
            );
        }

        $allowed = [$baseline];

        foreach ($manifest['populators'] ?? [] as $entry) {
            if (! empty($entry['executed']) && ! empty($entry['renamed_to'])) {
                $allowed[] = $entry['renamed_to'];
            }
        }

        $unexpected = array_values(array_diff($ran, $allowed));

        if ($unexpected !== []) {
            throw new RuntimeException(
                "Cannot unsquash because new migrations were executed after the baseline:\n  - "
                    . implode("\n  - ", $unexpected)
            );
        }
    }

    protected function latestArchive(): string
    {
        $root = rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'archive';

        if (! is_dir($root)) {
            throw new RuntimeException('No squash archive found.');
        }

        $directories = glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [];

        if ($directories === []) {
            throw new RuntimeException('No squash archive found.');
        }

        rsort($directories);

        return $directories[0];
    }

    protected function resolveArchive(string $archive): string
    {
        if (is_dir($archive)) {
            return $archive;
        }

        $path = rtrim($this->path, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'archive'
            . DIRECTORY_SEPARATOR
            . $archive;

        if (! is_dir($path)) {
            throw new RuntimeException(
                "Squash archive not found: {$archive}"
            );
        }

        return $path;
    }
}
