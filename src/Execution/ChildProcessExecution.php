<?php

declare(strict_types=1);

namespace Baretest\Execution;

use Baretest\Core\Execution;
use Baretest\Core\FileResult;
use Baretest\Core\TestFile;

/** Runs each test file in a fresh PHP process, so that a crash fails only that file. */
class ChildProcessExecution implements Execution
{
    private string $autoload;
    /** @var ?list<string> */
    private ?array $command = null;

    public function __construct(string $autoload)
    {
        $this->autoload = $autoload;
    }

    public function run(TestFile $file): FileResult
    {
        $recordsPath = (string) tempnam(sys_get_temp_dir(), 'baretest');
        $outputPath = (string) tempnam(sys_get_temp_dir(), 'baretest');
        try {
            $start = microtime(true);
            $process = proc_open(
                [...$this->buildCommand(), __DIR__ . '/Worker/worker.php', $file->getPath(), $recordsPath, $this->autoload],
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
        $result = new FileResult($file, $tests, $time, $output);
        return $crashed ? $result->crash($code) : $result;
    }

    /**
     * PHP with the php.ini of this process, or none, and a -d only for each setting that
     * differs from it: some values, like `a=href,form=`, do not survive the ini parser.
     * error_reporting is E_ALL unless it was given with -d, so that php.ini cannot hide errors.
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
            if (ini_get('error_reporting') === ($fresh['error_reporting'] ?? null)) {
                array_push($this->command, '-d', 'error_reporting=' . E_ALL);
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
