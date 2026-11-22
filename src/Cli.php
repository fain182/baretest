<?php

declare(strict_types=1);

namespace Baretest;

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
            $report = new Report(STDOUT, stream_isatty(STDOUT) && ($noColor === false || $noColor === ''));
            $results = new SuiteResult();
            foreach ($files as $file) {
                $results->append(Process::run($file, FileResult::makeRelative($file)));
                $report->printFile($results->getLast());
                Github::annotate($results->getLast());
            }
            $report->printSummary();
            Gitlab::save($results);
            return $results->hasFileWithoutTests() ? 2 : ($results->hasFailures() ? 1 : 0);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            fwrite(STDERR, 'baretest: ' . $e->getMessage() . "\n");
            return 2;
        }
    }
}
