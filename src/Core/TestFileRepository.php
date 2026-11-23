<?php

declare(strict_types=1);

namespace Baretest\Core;

interface TestFileRepository
{
    /** @return list<string> paths relative to the working directory, each file once */
    public function listFiles(): array;
}
