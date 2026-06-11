<?php

namespace Eril\Migraw;

use Eril\Migraw\Sql\SqlStatement;

abstract class Seeder
{
    abstract public function run(): string|array|SqlStatement;
}