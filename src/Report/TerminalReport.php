<?php

declare(strict_types=1);

namespace Baretest\Report;

use Baretest\Core\Comparison;
use Baretest\Core\FileResult;
use Baretest\Core\Location;
use Baretest\Core\Report;
use Baretest\Core\SuiteResult;
use Baretest\Core\TestResult;

/** The output described in the README, for people to read. */
class TerminalReport implements Report
{
    private const INDENT = '       ';
    private const LABEL = 'expected  ';
    private const DIFFERENCES = 5;
    private const BOLD = '1';
    private const DIM = '2';
    private const RED = '31';
    private const GREEN = '32';
    private const YELLOW = '33';

    /** @var resource */
    private $stream;
    private bool $color;
    /** @var array<string, list<string>> */
    private array $sources = [];

    /** @param resource $stream */
    public function __construct($stream, bool $color)
    {
        $this->stream = $stream;
        $this->color = $color;
    }

    public function addFile(FileResult $file): void
    {
        $ok = !$file->hasFailures() && !$file->hasNoTests();
        if ($file->hasNoTests()) {
            $summary = 'no tests';
        } else {
            $summary = $ok ? self::count($file->countTests(), 'test') : $file->countFailed() . ' of ' . $file->countTests() . ' failed';
        }
        $out = $this->mark($ok) . ' ' . $file->getFile()->getPath() . '  ' . self::addSkipped($summary, $file->countSkipped()) . "\n";
        foreach ($file->listTests() as $test) {
            if ($test->isSkipped()) {
                $out .= '  ' . $this->paint(self::YELLOW, '-') . ' ' . $test->getTitle() . '  ' . $test->getSkipReason() . '  ' . $this->paint(self::DIM, '(skipped)') . "\n";
            }
        }
        $failures = array_map([$this, 'renderFailure'], $file->listFailures());
        $out .= $failures === [] ? '' : "\n" . implode("\n", $failures) . "\n";
        fwrite($this->stream, $out . $this->renderOutput($file->getOutput()));
    }

    public function finish(SuiteResult $suite): void
    {
        $files = self::count($suite->countFiles(), 'file');
        $tests = self::count($suite->countTests(), 'test');
        $failedFiles = $suite->countFailedFiles();
        $emptyFiles = $suite->countEmptyFiles();
        if ($failedFiles === 0 && $emptyFiles === 0) {
            $text = $tests . ' in ' . $files;
        } elseif ($failedFiles === 0) {
            $text = $emptyFiles . ' of ' . $files . ' did not run any test';
        } else {
            $text = $suite->countFailed() . ' of ' . $tests . ' failed in ' . $failedFiles . ' of ' . $files
                . ($emptyFiles > 0 ? ', ' . $emptyFiles . ' did not run any test' : '');
        }
        $ok = $failedFiles === 0 && $emptyFiles === 0;
        $text = self::addSkipped($text, $suite->countSkipped());
        $time = sprintf('%.2fs', $suite->getTime());
        $rule = str_repeat('─', Columns::measure(($ok ? '✓' : '✗') . ' ' . $text . '  ' . $time));
        fwrite($this->stream, $this->paint(self::DIM, $rule) . "\n" . $this->mark($ok) . ' ' . $this->paint(self::BOLD, $text) . '  ' . $this->paint(self::DIM, $time) . "\n");
        $slowTests = $ok ? $suite->listSlowTests() : [];
        if ($slowTests !== []) {
            fwrite($this->stream, "\n" . $this->paint(self::YELLOW, '◷') . ' Tests feeling too slow? Start with:' . "\n");
        }
        foreach ($slowTests as $slow) {
            $seconds = $slow->getTest()->getTime();
            $share = round(100 * $seconds / max($suite->getTime(), $seconds));
            fwrite($this->stream, '  ' . $slow->getFile()->getPath() . ' › ' . $slow->getTest()->getTitle()
                . '  ' . sprintf('%.2fs', $seconds) . '  ' . $this->paint(self::DIM, '(' . $share . '% of the run)') . "\n");
        }
    }

    private function renderFailure(TestResult $test): string
    {
        $failure = $test->getFailure();
        $location = $failure->getLocation();
        $out = '  ' . $this->mark(false) . ' ' . $this->paint(self::BOLD, $test->getTitle())
            . ($location === null ? '' : '  ' . $this->paint(self::DIM, '(' . $location->format() . ')')) . "\n";
        if ($location !== null) {
            $out .= $this->renderSource($location);
        }
        $comparisons = $failure->listComparisons();
        return $out . "\n" . ($comparisons === [] ? self::INDENT . str_replace("\n", "\n" . self::INDENT, $failure->describe()) . "\n" : $this->renderComparisons($comparisons));
    }

    /** @param non-empty-list<Comparison> $comparisons */
    private function renderComparisons(array $comparisons): string
    {
        $shown = array_slice($comparisons, 0, self::DIFFERENCES);
        $width = max(array_map(static fn (Comparison $comparison): int => Columns::measure($comparison->getPath()), $shown));
        $out = implode("\n", array_map(fn (Comparison $comparison): string => $this->renderValues($comparison, $width), $shown));
        $more = count($comparisons) - count($shown);
        return $more === 0 ? $out : $out . "\n" . self::INDENT . $this->paint(self::DIM, $more . ' more elements differ') . "\n";
    }

    /** The lines of the call, without the indentation they share. */
    private function renderSource(Location $location): string
    {
        $file = $location->getFile();
        $this->sources[$file] ??= is_readable($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $lines = array_map('rtrim', array_slice($this->sources[$file], $location->getLine() - 1, $location->getLastLine() - $location->getLine() + 1));
        $indents = array_map(static fn (string $line): int => strspn($line, " \t"), array_filter($lines, static fn (string $line): bool => $line !== ''));
        $indent = $indents === [] ? 0 : min($indents);
        $width = strlen((string) ($location->getLine() + count($lines) - 1));
        $out = '';
        foreach ($lines as $i => $line) {
            $number = str_pad((string) ($location->getLine() + $i), $width, ' ', STR_PAD_LEFT);
            $out .= self::INDENT . $this->paint(self::DIM, $number . ' │') . ($line === '' ? '' : ' ' . substr($line, $indent)) . "\n";
        }
        return $out;
    }

    /** @param int $width of the widest path, to which every path is aligned on the right */
    private function renderValues(Comparison $comparison, int $width): string
    {
        $path = $comparison->getPath();
        $margin = self::INDENT . ($width === 0 ? '' : str_repeat(' ', $width - Columns::measure($path)) . $path . '  ');
        $blank = str_repeat(' ', Columns::measure($margin));
        [$expected, $got, $caret] = Columns::cut($comparison->getExpected(), $comparison->getGot(), $comparison->getCaret());
        $width = max(Columns::measure($expected), Columns::measure($got)) + 4;
        $pad = static fn (string $value): string => $value . str_repeat(' ', $width - Columns::measure($value));
        $out = $margin . self::LABEL . $pad($expected) . $this->paint(self::DIM, '(' . $comparison->getExpectedType() . ')') . "\n"
            . $blank . str_pad('got  ', strlen(self::LABEL), ' ', STR_PAD_LEFT) . $pad($got) . $this->paint(self::DIM, '(' . $comparison->getGotType() . ')') . "\n";
        return $caret === null ? $out : $out . $blank . str_repeat(' ', strlen(self::LABEL) + $caret) . $this->paint(self::RED, '^') . "\n";
    }

    private function renderOutput(string $output): string
    {
        if ($output === '') {
            return '';
        }
        $out = '  ' . $this->paint(self::DIM, 'output') . "\n";
        foreach (explode("\n", rtrim($output, "\n")) as $line) {
            $out .= self::INDENT . $this->paint(self::DIM, '│') . ($line === '' ? '' : ' ' . $line) . "\n";
        }
        return $out . "\n";
    }

    private function mark(bool $ok): string
    {
        return $ok ? $this->paint(self::GREEN, '✓') : $this->paint(self::RED, '✗');
    }

    private function paint(string $code, string $text): string
    {
        return $this->color ? "\e[{$code}m{$text}\e[0m" : $text;
    }

    private static function count(int $n, string $noun): string
    {
        return $n . ' ' . $noun . ($n === 1 ? '' : 's');
    }

    private static function addSkipped(string $text, int $skipped): string
    {
        return $skipped > 0 ? $text . ', ' . $skipped . ' skipped' : $text;
    }
}
