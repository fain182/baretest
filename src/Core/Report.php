<?php

declare(strict_types=1);

namespace Baretest\Core;

interface Report
{
    public function addFile(FileResult $file): void;

    public function finish(SuiteResult $suite): void;
}
