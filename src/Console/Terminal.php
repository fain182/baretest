<?php

declare(strict_types=1);

namespace Baretest\Console;

use Baretest\Core\FileResult;
use Baretest\Core\Report;
use Baretest\Core\SuiteResult;
use Baretest\Core\TestResult;

/** The output described in the README, for people to read. */
class Terminal implements Report
{
    private const INDENT = '       ';
    private const BOLD = '1';
    private const DIM = '2';
    private const RED = '31';
    private const GREEN = '32';

    private bool $color;

    public function __construct(bool $color)
    {
        $this->color = $color;
    }

    public function addFile(FileResult $file): void
    {
        $ok = !$file->hasFailures();
        $summary = $ok ? self::count($file->countTests(), 'test') : $file->countFailed() . ' of ' . $file->countTests() . ' failed';
        $out = $this->mark($ok) . ' ' . $file->getFile() . '  ' . $summary . '  ' . $this->formatTime($file->getTime()) . "\n";
        $blocks = array_map([$this, 'renderFailure'], $file->listFailures());
        fwrite(STDOUT, $out . ($blocks === [] ? '' : "\n" . implode("\n", $blocks) . "\n"));
    }

    public function finish(SuiteResult $suite): void
    {
        $ok = $suite->countFailedFiles() === 0;
        $files = self::count($suite->countFiles(), 'file');
        $text = $ok
            ? self::count($suite->countTests(), 'test') . ' in ' . $files
            : $suite->countFailed() . ' of ' . self::count($suite->countTests(), 'test') . ' failed in ' . $suite->countFailedFiles() . ' of ' . $files;
        fwrite(STDOUT, $this->mark($ok) . ' ' . $this->paint(self::BOLD, $text) . '  ' . $this->formatTime($suite->getTime()) . "\n");
    }

    private function renderFailure(TestResult $test): string
    {
        $out = '  ' . $this->mark(false) . ' ' . $this->paint(self::BOLD, $test->getTitle()) . "\n\n";
        foreach (explode("\n", $test->getFailure()->describe()) as $line) {
            $out .= self::INDENT . $line . "\n";
        }
        return $out;
    }

    private function mark(bool $ok): string
    {
        return $ok ? $this->paint(self::GREEN, '✓') : $this->paint(self::RED, '✗');
    }

    private function formatTime(float $seconds): string
    {
        return $this->paint(self::DIM, sprintf('%.2fs', $seconds));
    }

    private function paint(string $code, string $text): string
    {
        return $this->color ? "\e[{$code}m{$text}\e[0m" : $text;
    }

    private static function count(int $n, string $noun): string
    {
        return $n . ' ' . $noun . ($n === 1 ? '' : 's');
    }
}
