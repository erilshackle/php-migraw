<?php

namespace Eril\Migraw\Schema;

interface SchemaDriverDumper
{
    /**
     * @return array<string, string[]>
     */
    public function dump(): array;

    /**
     * @return string[]
     */
    public function beforeCreate(): array;

    /**
     * @return string[]
     */
    public function afterCreate(): array;

    /**
     * @return string[]
     */
    public function beforeDrop(): array;

    /**
     * @return string[]
     */
    public function afterDrop(): array;

    public function dropTable(string $table): string;
}