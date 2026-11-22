<?php

declare(strict_types=1);

namespace Baretest\Runner;

use Baretest\Output\Github;
use Baretest\Output\Gitlab;
use Baretest\Output\Report;
use Baretest\Result\FileResult;
use Baretest\Result\SuiteResult;

final class Cli
{
    /** @param list<string> $argv */
    public static function run(array $argv): int
    {
        try {
            if (!function_exists('proc_open')) {
                throw new \RuntimeException('proc_open() is disabled in php.ini (disable_functions), and baretest runs each test file in its own process');
            }
            $files = TestFiles::find(array_slice($argv, 1));

            $noColor = getenv('NO_COLOR');
            $report = new Report(stream_isatty(STDOUT) && ($noColor === false || $noColor === ''));
            $results = new SuiteResult();
            foreach ($files as $file) {
                $results->append(Process::run($file));
                $report->printFile($results->getLast());
                Github::annotate($results->getLast());
            }
            $report->printSummary($results);
            Gitlab::save($results);
            if ($results->countFiles(static fn (FileResult $file): bool => $file->hasNoTests()) > 0) {
                return 2;
            }
            return $results->countFiles(static fn (FileResult $file): bool => $file->hasFailures()) > 0 ? 1 : 0;
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            fwrite(STDERR, 'baretest: ' . $e->getMessage() . "\n");
            return 2;
        }
    }
}
