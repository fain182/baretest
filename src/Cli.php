<?php

declare(strict_types=1);

namespace Baretest;

use Baretest\Console\Terminal;
use Baretest\Core\Suite;
use Baretest\Filesystem\TestFiles;
use Baretest\Process\ChildProcess;

/** vendor/bin/baretest: picks the adapters for this environment and runs the suite. */
class Cli
{
    /** @param list<string> $argv */
    public static function run(array $argv, string $autoload): int
    {
        $suite = new Suite(new TestFiles(array_slice($argv, 1)), new ChildProcess($autoload), [new Terminal(false)]);
        $suite->run();
        return 0;
    }
}
