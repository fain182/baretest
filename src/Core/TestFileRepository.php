<?php

declare(strict_types=1);

namespace Baretest\Core;

interface TestFileRepository
{
    /** @return list<TestFile> each file once */
    public function listFiles(): array;
}
