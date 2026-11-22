<?php

declare(strict_types=1);

namespace Baretest\Output;

use Baretest\Result\Failure;
use Baretest\Result\FileResult;
use Baretest\Result\SuiteResult;

/** Writes the output described in the README, one file at a time. */
final class Report
{
    private const DETAIL_INDENT = '       ';
    private const BOLD = '1';
    private const DIM = '2';
    private const RED = '31';
    private const GREEN = '32';
    private const YELLOW = '33';

    private float $start;
    /** @var array<string, list<string>> */
    private array $sources = [];

    private bool $color;

    public function __construct(bool $color)
    {
        $this->color = $color;
        $this->start = microtime(true);
    }

    public function printFile(FileResult $result): void
    {
        $ok = !$result->hasFailures() && !$result->hasNoTests();
        $time = $this->paint(self::DIM, sprintf('%.2fs', $result->time));
        $header = $this->mark($ok) . ' ' . $result->file . '  ' . $this->summarize($result, $ok) . '  ' . $time . "\n";
        fwrite(STDOUT, $header . $this->renderEntries($result) . $this->renderOutput($result));
    }

    public function printSummary(SuiteResult $results): void
    {
        $time = $this->paint(self::DIM, sprintf('%.2fs', microtime(true) - $this->start));
        $files = $results->countFiles();
        $failedFiles = $results->countFiles(static fn (FileResult $file): bool => $file->hasFailures());
        $emptyFiles = $results->countFiles(static fn (FileResult $file): bool => $file->hasNoTests());
        $tests = $results->sum(static fn (FileResult $file): int => $file->countTests());
        $skipped = $results->sum(static fn (FileResult $file): int => $file->countSkipped());
        $ok = $failedFiles === 0 && $emptyFiles === 0;
        if ($ok) {
            $text = self::count($tests, 'test') . ' in ' . self::count($files, 'file');
        } elseif ($failedFiles === 0) {
            $text = $emptyFiles . ' of ' . self::count($files, 'file') . ' did not run any test';
        } else {
            $text = $results->sum(static fn (FileResult $file): int => $file->countFailed()) . ' of ' . self::count($tests, 'test')
                . ' failed in ' . $failedFiles . ' of ' . self::count($files, 'file')
                . ($emptyFiles > 0 ? ', ' . $emptyFiles . ' did not run any test' : '');
        }
        fwrite(STDOUT, $this->mark($ok) . ' ' . $this->paint(self::BOLD, self::addSkipped($text, $skipped)) . '  ' . $time . "\n");
    }

    private function summarize(FileResult $result, bool $ok): string
    {
        if ($result->hasNoTests()) {
            $text = 'no tests';
        } else {
            $text = $ok ? self::count($result->countTests(), 'test') : $result->countFailed() . ' of ' . $result->countTests() . ' failed';
        }
        return self::addSkipped($text, $result->countSkipped());
    }

    private static function addSkipped(string $text, int $skipped): string
    {
        return $skipped > 0 ? $text . ', ' . $skipped . ' skipped' : $text;
    }

    /** The skipped tests of a file, then its failures, each in the order their tests started. */
    private function renderEntries(FileResult $result): string
    {
        $skips = '';
        foreach ($result->tests as $test) {
            if ($test->skipped !== null) {
                $skips .= '  ' . $this->paint(self::YELLOW, '-') . ' ' . $test->title . '  ' . $test->skipped . '  ' . $this->paint(self::DIM, '(skipped)') . "\n";
            }
        }
        $out = '';
        foreach ($result->listFailures() as $test) {
            $out .= $this->renderBlock($test->title, $test->failure) . "\n";
        }
        return $skips . ($out === '' ? '' : "\n" . $out);
    }

    private function renderOutput(FileResult $result): string
    {
        if ($result->output === '') {
            return '';
        }
        $out = '  ' . $this->paint(self::DIM, 'output') . "\n";
        foreach (explode("\n", rtrim($result->output, "\n")) as $line) {
            $out .= self::DETAIL_INDENT . $this->paint(self::DIM, '│') . ($line === '' ? '' : ' ' . $line) . "\n";
        }
        return $out . "\n";
    }

    private function renderBlock(string $title, Failure $failure): string
    {
        $location = $failure->formatLocation();
        $out = '  ' . $this->mark(false) . ' ' . $this->paint(self::BOLD, $title);
        if ($location !== null) {
            $out .= '  ' . $this->paint(self::DIM, '(' . $location . ')');
        }
        $out .= "\n";
        $source = $location !== null ? $this->readSource($failure->file, $failure->line, $failure->lastLine) : [];
        $width = strlen((string) array_key_last($source));
        foreach ($source as $number => $code) {
            $out .= self::DETAIL_INDENT . $this->paint(self::DIM, str_pad((string) $number, $width, ' ', STR_PAD_LEFT) . ' │') . ($code === '' ? '' : ' ' . $code) . "\n";
        }
        return $out . "\n" . ($failure->expected !== null ? $this->renderValues($failure) : $this->renderMessage($failure));
    }

    private function renderMessage(Failure $failure): string
    {
        $lines = explode("\n", $failure->describe());
        return self::DETAIL_INDENT . implode("\n" . self::DETAIL_INDENT, $lines) . "\n";
    }

    private function renderValues(Failure $failure): string
    {
        [$expected, $got, $caret] = Columns::cut($failure->expected, $failure->got, $failure->caret);
        $width = max(Columns::measure($expected), Columns::measure($got)) + 4;
        $pad = static fn (string $value): string => $value . str_repeat(' ', $width - Columns::measure($value));
        $label = self::DETAIL_INDENT . 'expected  ';

        $out = $label . $pad($expected) . $this->paint(self::DIM, '(' . $failure->expectedType . ')') . "\n"
            . str_pad('got  ', strlen($label), ' ', STR_PAD_LEFT) . $pad($got) . $this->paint(self::DIM, '(' . $failure->gotType . ')') . "\n";
        return $caret === null ? $out : $out . str_repeat(' ', strlen($label) + $caret) . $this->paint(self::RED, '^') . "\n";
    }

    /** @return array<int, string> lines $first to $last by number, without the indentation they share */
    private function readSource(string $file, int $first, int $last): array
    {
        $this->sources[$file] ??= is_readable($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $lines = [];
        for ($number = $first; $number <= $last && isset($this->sources[$file][$number - 1]); $number++) {
            $lines[$number] = rtrim($this->sources[$file][$number - 1]);
        }
        $indents = array_map(static fn (string $code): int => strspn($code, " \t"), array_filter($lines, static fn (string $code): bool => $code !== ''));
        $indent = $indents === [] ? 0 : min($indents);
        return array_map(static fn (string $code): string => (string) substr($code, $indent), $lines);
    }

    private static function count(int $n, string $noun): string
    {
        return $n . ' ' . $noun . ($n === 1 ? '' : 's');
    }

    private function mark(bool $ok): string
    {
        return $ok ? $this->paint(self::GREEN, '✓') : $this->paint(self::RED, '✗');
    }

    private function paint(string $code, string $text): string
    {
        return $this->color ? "\e[{$code}m{$text}\e[0m" : $text;
    }
}
