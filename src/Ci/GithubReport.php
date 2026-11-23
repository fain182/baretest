<?php

declare(strict_types=1);

namespace Baretest\Ci;

use Baretest\Core\FileResult;
use Baretest\Core\Report;
use Baretest\Core\SuiteResult;

/** The workflow commands that GitHub Actions shows as annotations on the failing lines. */
class GithubReport implements Report
{
    /** @var resource */
    private $stream;

    /** @param resource $stream */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    public function addFile(FileResult $file): void
    {
        foreach ($file->listFailures() as $test) {
            $failure = $test->getFailure();
            $location = $failure->getLocation();
            $properties = 'file=' . self::escape($location === null ? $file->getFile() : $location->getFile(), true)
                . ($location === null ? '' : ',line=' . $location->getLine())
                . ',title=' . self::escape($test->getTitle(), true);
            fwrite($this->stream, '::error ' . $properties . '::' . self::escape($failure->describe(), false) . "\n");
        }
    }

    public function finish(SuiteResult $suite): void
    {
    }

    private static function escape(string $text, bool $property): string
    {
        $escapes = ['%' => '%25', "\r" => '%0D', "\n" => '%0A'] + ($property ? [':' => '%3A', ',' => '%2C'] : []);
        return strtr($text, $escapes);
    }
}
