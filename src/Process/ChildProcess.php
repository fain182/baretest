<?php

declare(strict_types=1);

namespace Baretest\Process;

use Baretest\Core\Execution;
use Baretest\Core\Failure;
use Baretest\Core\FileResult;
use Baretest\Core\TestResult;

/** Runs each test file in a fresh PHP process, so that a crash fails only that file. */
class ChildProcess implements Execution
{
    private const CRASH_LINES = 10;

    private string $autoload;
    /** @var ?list<string> */
    private ?array $command = null;

    public function __construct(string $autoload)
    {
        $this->autoload = $autoload;
    }

    public function run(string $file): FileResult
    {
        $recordsPath = (string) tempnam(sys_get_temp_dir(), 'baretest');
        $outputPath = (string) tempnam(sys_get_temp_dir(), 'baretest');
        try {
            $start = microtime(true);
            $process = proc_open(
                [...$this->buildCommand(), dirname(__DIR__) . '/Worker/worker.php', $file, $recordsPath, $this->autoload],
                [0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], 1 => ['file', $outputPath, 'w'], 2 => ['redirect', 1]],
                $pipes,
            );
            if ($process === false) {
                throw new \RuntimeException('could not start ' . PHP_BINARY);
            }
            $code = proc_close($process);
            $time = microtime(true) - $start;
            $records = new Records($recordsPath);
            $tests = $records->readTests();
            $output = (string) file_get_contents($outputPath);
            $crashed = !$records->hasFileFailure() && (!$records->isClosed() || $code !== 0);
        } finally {
            unlink($recordsPath);
            unlink($outputPath);
        }
        if ($crashed) {
            $tests[] = new TestResult('crashed', 0.0, new Failure(self::describeCrash($code, $output)));
            $output = '';
        }
        return new FileResult($file, $tests, $time, $output);
    }

    private static function describeCrash(int $code, string $output): string
    {
        $message = "the process ended unexpectedly (exit code $code)";
        $lines = array_slice(explode("\n", trim($output)), -self::CRASH_LINES);
        return trim($output) === '' ? $message : $message . "\n" . implode("\n", $lines);
    }

    /**
     * PHP with the php.ini of this process, or none, and a -d only for each setting that
     * differs from it: some values, like `a=href,form=`, do not survive the ini parser.
     *
     * @return list<string>
     */
    private function buildCommand(): array
    {
        if ($this->command === null) {
            $ini = php_ini_loaded_file();
            $this->command = $ini === false ? [PHP_BINARY, '-n'] : [PHP_BINARY, '-c', $ini];
            $fresh = $this->readSettings($this->command);
            foreach (ini_get_all(null, false) as $name => $value) {
                if ($value !== null && ($fresh[$name] ?? null) !== $value) {
                    array_push($this->command, '-d', "$name=$value");
                }
            }
        }
        return $this->command;
    }

    /** @param list<string> $php @return array<string, ?string> */
    private function readSettings(array $php): array
    {
        $probe = proc_open([...$php, '-r', 'echo serialize(ini_get_all(null, false));'], [1 => ['pipe', 'w']], $pipes);
        if ($probe === false) {
            throw new \RuntimeException('could not start ' . PHP_BINARY);
        }
        $settings = @unserialize((string) stream_get_contents($pipes[1]), ['allowed_classes' => false]);
        proc_close($probe);
        return is_array($settings) ? $settings : [];
    }
}
