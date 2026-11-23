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
        try {
            if (!function_exists('proc_open')) {
                throw new \RuntimeException('proc_open() is disabled in php.ini (disable_functions), and baretest runs each test file in its own process');
            }
            $noColor = getenv('NO_COLOR');
            $reports = [new Terminal(STDOUT, stream_isatty(STDOUT) && ($noColor === false || $noColor === ''))];
            $suite = new Suite(new TestFiles(array_slice($argv, 1)), new ChildProcess($autoload), $reports);
            return $suite->run()->computeExitCode();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            fwrite(STDERR, 'baretest: ' . $e->getMessage() . "\n");
            return 2;
        }
    }
}
