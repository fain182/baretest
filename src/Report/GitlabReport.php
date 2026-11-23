<?php

declare(strict_types=1);

namespace Baretest\Report;

use Baretest\Core\FileResult;
use Baretest\Core\Report;
use Baretest\Core\SuiteResult;
use Baretest\Core\TestResult;

/** The JUnit XML report that GitLab reads. */
class GitlabReport implements Report
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function addFile(FileResult $file): void
    {
    }

    public function finish(SuiteResult $suite): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<testsuites>\n";
        foreach ($suite->listFiles() as $file) {
            $xml .= sprintf(
                "  <testsuite name=\"%s\" tests=\"%d\" failures=\"%d\" skipped=\"%d\" time=\"%.3f\">\n",
                self::escape($file->getFile()),
                $file->countTests(),
                $file->countFailed(),
                $file->countSkipped(),
                $file->getTime(),
            );
            foreach ($file->listTests() as $test) {
                $xml .= self::renderTestCase($file, $test);
            }
            $xml .= "  </testsuite>\n";
        }
        if (@file_put_contents($this->path, $xml . "</testsuites>\n") === false) {
            throw new \RuntimeException('cannot write ' . $this->path . ': ' . (error_get_last()['message'] ?? 'unknown error'));
        }
    }

    private static function renderTestCase(FileResult $file, TestResult $test): string
    {
        $open = sprintf('    <testcase classname="%s" name="%s" time="%.3f"', self::escape($file->getFile()), self::escape($test->getTitle()), $test->getTime());
        if ($test->isSkipped()) {
            return $open . ">\n      <skipped message=\"" . self::escape((string) $test->getSkipReason()) . "\"/>\n    </testcase>\n";
        }
        $cause = $test->findCause();
        if ($cause === null) {
            return $open . "/>\n";
        }
        $location = $cause->getLocation() === null ? $file->getFile() : $cause->getLocation()->format();
        $message = $cause->describe();
        return $open . ">\n      <failure message=\"" . self::escape(explode("\n", $message)[0]) . '">'
            . self::escape($location . "\n" . $message) . "</failure>\n    </testcase>\n";
    }

    private static function escape(string $text): string
    {
        $text = (string) preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', '', $text);
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
