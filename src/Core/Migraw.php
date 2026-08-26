<?php

namespace Eril\Migraw\Core;

use Throwable;

final class Migraw
{
    protected CliOptions $options;
    protected PathResolver $paths;
    protected Config $config;
    protected ConnectionResolver $connections;

    public function run(array $argv): int
    {
        $this->options = CliOptions::fromArgv($argv);
        $this->paths = new PathResolver();
        $this->config = new Config($this->paths);
        $this->connections = new ConnectionResolver($this->paths);

        try {
            $this->dispatch();
            return 0;
        } catch (Throwable $e) {
            echo Console::red("Error: {$e->getMessage()}\n");
            return 1;
        }
    }

    protected function dispatch(): void
    {
        $command = $this->options->command();

        if (in_array($command, ['init', '--init'], true)) {
            $this->config->init(
                $this->options->has('--force')
            );

            return;
        }

        if ($command !== null && str_starts_with($command, 'init:')) {
            $driver = substr($command, strlen('init:'));

            $this->config->init(
                $this->options->has('--force'),
                $driver
            );

            return;
        }

        $context = RuntimeContext::boot(
            $this->config,
            $this->connections,
            $this->paths
        );

        $handler = new CommandHandler(
            $context,
            $this->options,
            $this->paths
        );

        $handler->handle($command);
    }
}