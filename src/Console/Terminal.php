<?php

declare(strict_types=1);

namespace Baretest\Console;

use Baretest\Core\FileResult;
use Baretest\Core\Report;
use Baretest\Core\SuiteResult;

/** The output described in the README, for people to read. */
class Terminal implements Report
{
    private const DIM = '2';
    private const GREEN = '32';

    private bool $color;

    public function __construct(bool $color)
    {
        $this->color = $color;
    }

    public function addFile(FileResult $file): void
    {
        $this->write($this->mark() . ' ' . $file->getFile() . '  ' . self::count($file->countTests(), 'test') . '  ' . $this->formatTime($file->getTime()));
    }

    public function finish(SuiteResult $suite): void
    {
        $text = self::count($suite->countTests(), 'test') . ' in ' . self::count($suite->countFiles(), 'file');
        $this->write($this->mark() . ' ' . $text . '  ' . $this->formatTime($suite->getTime()));
    }

    private function write(string $line): void
    {
        fwrite(STDOUT, $line . "\n");
    }

    private function mark(): string
    {
        return $this->paint(self::GREEN, '✓');
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
