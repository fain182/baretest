<?php

declare(strict_types=1);

namespace Baretest;

/** Writes the output described in the README, one file at a time. */
final class Report
{
    private const VALUE_WIDTH = 60;
    private const DETAIL_INDENT = '       ';

    private int $files = 0;
    private int $failedFiles = 0;
    private int $tests = 0;
    private int $failed = 0;
    private int $emptyFiles = 0;
    private float $start;
    /** @var list<FileResult> */
    private array $results = [];
    /** @var array<string, list<string>> */
    private array $sources = [];

    /** @param resource $out */
    public function __construct(private $out, private bool $color, private ?string $ci)
    {
        $this->start = microtime(true);
    }

    public static function makeRelative(string $path): string
    {
        $prefix = getcwd() . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    /** file:line of a failure, relative to the working directory. */
    public static function formatLocation(array $failure): ?string
    {
        if (!isset($failure['file'])) {
            return null;
        }
        return self::makeRelative($failure['file']) . (isset($failure['line']) ? ':' . $failure['line'] : '');
    }

    public static function describe(array $failure): string
    {
        $label = $failure['label'] ?? null;
        return $label === null ? $failure['message'] : ($failure['message'] === '' ? $label : $label . ': ' . $failure['message']);
    }

    public function add(FileResult $result): void
    {
        $this->results[] = $result;
        $this->files++;
        $this->tests += $result->countTests();
        $this->failed += $result->countFailed();

        $failures = $result->listFailures();
        $ok = $failures === [] && !$result->hasNoTests();
        if ($result->hasNoTests()) {
            $this->emptyFiles++;
        } elseif (!$ok) {
            $this->failedFiles++;
        }

        $time = $this->paint('2', sprintf('%.2fs', $result->time));
        $this->write($this->mark($ok) . ' ' . $result->file . '  ' . $this->summarize($result, $ok) . '  ' . $time . "\n");
        $this->write($this->renderEntries($result));
        $this->write($this->renderOutput($result));

        if ($this->ci === 'github') {
            foreach ($failures as $entry) {
                $this->write(self::renderAnnotation($result, $entry['title'], $entry['failure']) . "\n");
            }
        }
    }

    public function finish(): void
    {
        $time = $this->paint('2', sprintf('%.2fs', microtime(true) - $this->start));
        $ok = $this->failedFiles === 0 && $this->emptyFiles === 0;
        $text = match (true) {
            $ok => self::count($this->tests, 'test') . ' in ' . self::count($this->files, 'file'),
            $this->failedFiles === 0 => $this->emptyFiles . ' of ' . self::count($this->files, 'file') . ' did not run any test',
            default => $this->failed . ' of ' . self::count($this->tests, 'test') . ' failed in ' . $this->failedFiles . ' of ' . self::count($this->files, 'file')
                . ($this->emptyFiles > 0 ? ', ' . $this->emptyFiles . ' did not run any test' : ''),
        };
        $this->write($this->mark($ok) . ' ' . $text . '  ' . $time . "\n");
        if ($this->ci === 'gitlab') {
            fwrite(STDOUT, Junit::render($this->results));
        }
    }

    public function getExitCode(): int
    {
        return $this->emptyFiles > 0 ? 2 : ($this->failedFiles > 0 ? 1 : 0);
    }

    private function summarize(FileResult $result, bool $ok): string
    {
        return match (true) {
            $result->hasNoTests() => 'no tests',
            $ok => self::count($result->countTests(), 'test'),
            default => $result->countFailed() . ' of ' . $result->countTests() . ' failed',
        };
    }

    /** The failures of a file, in the order their tests started. */
    private function renderEntries(FileResult $result): string
    {
        $out = '';
        foreach ($result->tests as $test) {
            if ($test['failure'] !== null) {
                $out .= $this->renderBlock(FileResult::formatTitle($test), $test['failure']) . "\n";
            }
        }
        if ($result->stopped !== null) {
            $out .= $this->renderBlock($result->stopped['title'], $result->stopped['failure']) . "\n";
        }
        return $out === '' ? '' : "\n" . $out;
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
        $location = self::formatLocation($failure);
        $line = $failure['line'] ?? null;
        $out = '  ' . $this->mark(false) . ' ' . $title;
        if ($location !== null) {
            $out .= '  ' . $this->paint('2', '(' . $location . ')');
        }
        $out .= "\n";
        $source = $location !== null && $line !== null ? $this->readSourceLine($failure['file'], $line) : null;
        if ($source !== null) {
            $out .= self::DETAIL_INDENT . $this->paint('2', $line . ' │') . ' ' . $source . "\n";
        }
        return $out . "\n" . (isset($failure['expected']) ? $this->renderValues($failure) : $this->renderMessage($failure));
    }

    private function renderMessage(array $failure): string
    {
        $lines = explode("\n", self::describe($failure));
        return self::DETAIL_INDENT . implode("\n" . self::DETAIL_INDENT, $lines) . "\n";
    }

    private function renderValues(array $failure): string
    {
        [$expected, $got, $caret] = self::cut($failure['expected'], $failure['got'], $failure['caret']);
        $width = max(Value::measureWidth($expected), Value::measureWidth($got)) + 4;
        $pad = static fn (string $value): string => $value . str_repeat(' ', $width - Value::measureWidth($value));
        $label = self::DETAIL_INDENT . 'expected  ';

        $out = $label . $pad($expected) . $this->paint('2', '(' . $failure['expectedType'] . ')') . "\n"
            . str_pad('got  ', strlen($label), ' ', STR_PAD_LEFT) . $pad($got) . $this->paint('2', '(' . $failure['gotType'] . ')') . "\n";
        return $caret === null ? $out : $out . str_repeat(' ', strlen($label) + $caret) . $this->paint('31', '^') . "\n";
    }

    /** Cuts long values to a window around the caret, the same window for both. */
    private static function cut(string $expected, string $got, ?int $caret): array
    {
        $a = self::splitCharacters($expected);
        $b = self::splitCharacters($got);
        if (max(count($a), count($b)) <= self::VALUE_WIDTH) {
            return [$expected, $got, $caret];
        }
        $index = 0;
        for ($column = 0; $index < count($b) && $column < ($caret ?? 0); $index++) {
            $column += Value::measureWidth($b[$index]);
        }
        $start = max(0, $index - 20);
        $window = static fn (array $characters): string => ($start > 0 ? '…' : '')
            . implode('', array_slice($characters, $start, self::VALUE_WIDTH))
            . ($start + self::VALUE_WIDTH < count($characters) ? '…' : '');
        $before = ($start > 0 ? '…' : '') . implode('', array_slice($b, $start, $index - $start));
        return [$window($a), $window($b), $caret === null ? null : Value::measureWidth($before)];
    }

    private static function splitCharacters(string $text): array
    {
        return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: str_split($text);
    }

    private function readSourceLine(string $file, int $line): ?string
    {
        $this->sources[$file] ??= is_readable($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [];
        $source = $this->sources[$file][$line - 1] ?? null;
        return $source === null ? null : trim($source);
    }

    private static function renderAnnotation(FileResult $result, string $title, array $failure): string
    {
        $file = isset($failure['file']) ? self::makeRelative($failure['file']) : $result->file;
        $properties = 'file=' . self::escape($file, true)
            . (isset($failure['line']) ? ',line=' . $failure['line'] : '')
            . ',title=' . self::escape($title, true);
        return '::error ' . $properties . '::' . self::escape(self::describe($failure), false);
    }

    private static function escape(string $text, bool $property): string
    {
        $from = ['%', "\r", "\n"];
        $to = ['%25', '%0D', '%0A'];
        if ($property) {
            array_push($from, ':', ',');
            array_push($to, '%3A', '%2C');
        }
        return str_replace($from, $to, $text);
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
