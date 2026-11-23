<?php

declare(strict_types=1);

namespace Baretest\Process;

use Baretest\Core\Execution;
use Baretest\Core\FileResult;

/** Runs each test file in a fresh PHP process. */
class ChildProcess implements Execution
{
    private string $autoload;

    public function __construct(string $autoload)
    {
        $this->autoload = $autoload;
    }

    public function run(string $file): FileResult
    {
        $records = (string) tempnam(sys_get_temp_dir(), 'baretest');
        $output = (string) tempnam(sys_get_temp_dir(), 'baretest');
        $start = microtime(true);
        $process = proc_open(
            [PHP_BINARY, dirname(__DIR__) . '/Worker/worker.php', $file, $records, $this->autoload],
            [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $output, 'w'], 2 => ['redirect', 1]],
            $pipes,
        );
        proc_close($process);
        $result = new FileResult($file, (new Records($records))->readTests(), microtime(true) - $start, (string) file_get_contents($output));
        unlink($records);
        unlink($output);
        return $result;
    }
}
