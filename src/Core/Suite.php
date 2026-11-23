<?php

declare(strict_types=1);

namespace Baretest\Core;

/** Runs every test file and tells each report what happened. */
class Suite
{
    private TestFileRepository $files;
    private Execution $execution;
    /** @var list<Report> */
    private array $reports;

    /** @param list<Report> $reports */
    public function __construct(TestFileRepository $files, Execution $execution, array $reports)
    {
        $this->files = $files;
        $this->execution = $execution;
        $this->reports = $reports;
    }

    public function run(): SuiteResult
    {
        $start = microtime(true);
        $results = [];
        foreach ($this->files->listFiles() as $file) {
            $results[] = $result = $this->execution->run($file);
            foreach ($this->reports as $report) {
                $report->addFile($result);
            }
        }
        $suite = new SuiteResult($results, microtime(true) - $start);
        foreach ($this->reports as $report) {
            $report->finish($suite);
        }
        return $suite;
    }
}
