<?php

declare(strict_types=1);

namespace Baretest;

use Baretest\Ci\GithubReport;
use Baretest\Ci\GitlabReport;
use Baretest\Console\TerminalReport;
use Baretest\Core\Suite;
use Baretest\Filesystem\FilesystemTestFileRepository;
use Baretest\Process\ChildProcessExecution;

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
            $reports = [new TerminalReport(STDOUT, stream_isatty(STDOUT) && ($noColor === false || $noColor === ''))];
            if (getenv('GITHUB_ACTIONS') === 'true') {
                $reports[] = new GithubReport(STDOUT);
            }
            if (getenv('GITLAB_CI') === 'true') {
                $reports[] = new GitlabReport('baretest-junit.xml');
            }
            $suite = new Suite(new FilesystemTestFileRepository(array_slice($argv, 1)), new ChildProcessExecution($autoload), $reports);
            return $suite->run()->computeExitCode();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            fwrite(STDERR, 'baretest: ' . $e->getMessage() . "\n");
            return 2;
        }
    }
}
