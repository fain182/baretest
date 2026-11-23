<?php

declare(strict_types=1);

namespace Baretest\Core;

interface Execution
{
    public function run(string $file): FileResult;
}
