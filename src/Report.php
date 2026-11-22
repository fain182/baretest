<?php

declare(strict_types=1);

namespace Baretest;

/** Writes the output described in the README, one file at a time. */
final class Report
{
    private const DETAIL_INDENT = '       ';

    private int $files = 0;
    private int $failedFiles = 0;
    private int $tests = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private int $emptyFiles = 0;
    private float $start;
    /** @var array<string, list<string>> */
    private array $sources = [];

    /** @var resource */
    private $out;
    private bool $color;

    /** @param resource $out */
    public function __construct($out, bool $color)
    {
        $this->out = $out;
        $this->color = $color;
        $this->start = microtime(true);
    }

    public function add(FileResult $result): void
    {
        $this->files++;
        $this->tests += $result->countTests();
        $this->failed += $result->countFailed();
        $this->skipped += $result->countSkipped();

        $ok = $result->listFailures() === [] && !$result->hasNoTests();
        if ($result->hasNoTests()) {
            $this->emptyFiles++;
        } elseif (!$ok) {
            $this->failedFiles++;
        }

        $time = $this->paint('2', sprintf('%.2fs', $result->time));
        $this->write($this->mark($ok) . ' ' . $result->file . '  ' . $this->summarize($result, $ok) . '  ' . $time . "\n");
        $this->write($this->renderEntries($result));
        $this->write($this->renderOutput($result));
    }

    public function finish(): void
    {
        $time = $this->paint('2', sprintf('%.2fs', microtime(true) - $this->start));
        $ok = $this->failedFiles === 0 && $this->emptyFiles === 0;
        if ($ok) {
            $text = self::count($this->tests, 'test') . ' in ' . self::count($this->files, 'file');
        } elseif ($this->failedFiles === 0) {
            $text = $this->emptyFiles . ' of ' . self::count($this->files, 'file') . ' did not run any test';
        } else {
            $text = $this->failed . ' of ' . self::count($this->tests, 'test') . ' failed in ' . $this->failedFiles . ' of ' . self::count($this->files, 'file')
                . ($this->emptyFiles > 0 ? ', ' . $this->emptyFiles . ' did not run any test' : '');
        }
        $text .= $this->skipped > 0 ? ', ' . $this->skipped . ' skipped' : '';
        $this->write($this->mark($ok) . ' ' . $this->paint('1', $text) . '  ' . $time . "\n");
    }

    public function getExitCode(): int
    {
        return $this->emptyFiles > 0 ? 2 : ($this->failedFiles > 0 ? 1 : 0);
    }

    private function summarize(FileResult $result, bool $ok): string
    {
        if ($result->hasNoTests()) {
            $text = 'no tests';
        } else {
            $text = $ok ? self::count($result->countTests(), 'test') : $result->countFailed() . ' of ' . $result->countTests() . ' failed';
        }
        $skipped = $result->countSkipped();
        return $skipped > 0 ? $text . ', ' . $skipped . ' skipped' : $text;
    }

    /** The skipped tests of a file, then its failures, each in the order their tests started. */
    private function renderEntries(FileResult $result): string
    {
        $skips = '';
        foreach ($result->tests as $test) {
            if ($test['skipped'] !== null) {
                $skips .= '  ' . $this->paint('33', '-') . ' ' . FileResult::formatTitle($test) . '  ' . $test['skipped'] . '  ' . $this->paint('2', '(skipped)') . "\n";
            }
        }
        $out = '';
        foreach ($result->listFailures() as $entry) {
            $out .= $this->renderBlock($entry['title'], $entry['failure']) . "\n";
        }
        return $skips . ($out === '' ? '' : "\n" . $out);
    }

    private function renderOutput(FileResult $result): string
    {
        if ($result->output === '') {
            return '';
        }
        $out = '  ' . $this->paint('2', 'output') . "\n";
        foreach (explode("\n", rtrim($result->output, "\n")) as $line) {
            $out .= self::DETAIL_INDENT . $this->paint('2', '│') . ($line === '' ? '' : ' ' . $line) . "\n";
        }
        return $out . "\n";
    }

    private function renderBlock(string $title, array $failure): string
    {
        $location = FileResult::formatLocation($failure);
        $line = $failure['line'] ?? null;
        $out = '  ' . $this->mark(false) . ' ' . $this->paint('1', $title);
        if ($location !== null) {
            $out .= '  ' . $this->paint('2', '(' . $location . ')');
        }
        $out .= "\n";
        $source = $location !== null && $line !== null ? $this->readSource($failure['file'], $line, $failure['lastLine'] ?? $line) : [];
        $width = strlen((string) array_key_last($source));
        foreach ($source as $number => $code) {
            $out .= self::DETAIL_INDENT . $this->paint('2', str_pad((string) $number, $width, ' ', STR_PAD_LEFT) . ' │') . ($code === '' ? '' : ' ' . $code) . "\n";
        }
        return $out . "\n" . (isset($failure['expected']) ? $this->renderValues($failure) : $this->renderMessage($failure));
    }

    private function renderMessage(array $failure): string
    {
        $lines = explode("\n", FileResult::describe($failure));
        return self::DETAIL_INDENT . implode("\n" . self::DETAIL_INDENT, $lines) . "\n";
    }

    private function renderValues(array $failure): string
    {
        [$expected, $got, $caret] = Value::cut($failure['expected'], $failure['got'], $failure['caret']);
        $width = max(Value::measureWidth($expected), Value::measureWidth($got)) + 4;
        $pad = static fn (string $value): string => $value . str_repeat(' ', $width - Value::measureWidth($value));
        $label = self::DETAIL_INDENT . 'expected  ';

        $out = $label . $pad($expected) . $this->paint('2', '(' . $failure['expectedType'] . ')') . "\n"
            . str_pad('got  ', strlen($label), ' ', STR_PAD_LEFT) . $pad($got) . $this->paint('2', '(' . $failure['gotType'] . ')') . "\n";
        return $caret === null ? $out : $out . str_repeat(' ', strlen($label) + $caret) . $this->paint('31', '^') . "\n";
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
        return $ok ? $this->paint('32', '✓') : $this->paint('31', '✗');
    }

    private function paint(string $code, string $text): string
    {
        return $this->color ? "\e[{$code}m{$text}\e[0m" : $text;
    }

    private function write(string $text): void
    {
        fwrite($this->out, $text);
    }
}
