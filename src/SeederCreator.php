<?php

namespace Eril\Migraw;

use RuntimeException;

class SeederCreator
{
    public function __construct(
        protected string $path
    ) {}

    public function create(string $name): string
    {
        if (! is_dir($this->path)) {
            mkdir($this->path, 0775, true);
        }

        $name = $this->normalizeName($name);

        $filename = "{$name}.php";
        $path = rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($path)) {
            throw new RuntimeException("Seeder already exists: {$path}");
        }

        file_put_contents($path, $this->stub());

        return $path;
    }

    protected function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Seeder name cannot be empty.');
        }

        return str_ends_with($name, 'Seeder')
            ? $name
            : $name . 'Seeder';
    }

    protected function stub(): string
    {
        return <<<'PHP'
<?php

use Eril\Migraw\Seeder;

return new class extends Seeder
{
    public function run(): string|array
    {
        return <<<SQL
        -- Write your seed SQL here
        SQL;
    }
};

PHP;
    }
}