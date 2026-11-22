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
            $results = [];
            foreach ($files as $file) {
                $results[] = $result = Process::run($file, FileResult::makeRelative($file));
                $report->printFile($result);
                Github::annotate($result);
            }
            $report->printSummary();
            Junit::save($results);
            return self::computeExitCode($results);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            fwrite(STDERR, 'baretest: ' . $e->getMessage() . "\n");
            return 2;
        }
    }

    /** @param list<FileResult> $results */
    private static function computeExitCode(array $results): int
    {
        $code = 0;
        foreach ($results as $result) {
            if ($result->hasNoTests()) {
                return 2;
            }
            if ($result->hasFailures()) {
                $code = 1;
            }
        }
        return $code;
    }
}
