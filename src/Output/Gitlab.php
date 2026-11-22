<?php

declare(strict_types=1);

namespace Baretest\Output;

use Baretest\Result\Failure;
use Baretest\Result\SuiteResult;

/** The JUnit XML report that GitLab reads. */
class Gitlab
{
    private const FILE = 'baretest-junit.xml';

    public static function save(SuiteResult $results): void
    {
        if (getenv('GITLAB_CI') === 'true' && @file_put_contents(self::FILE, self::render($results)) === false) {
            throw new \RuntimeException('cannot write ' . self::FILE . ': ' . error_get_last()['message']);
        }
    }

    private static function render(SuiteResult $results): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<testsuites>\n";
        foreach ($results as $result) {
            $xml .= sprintf(
                "  <testsuite name=\"%s\" tests=\"%d\" failures=\"%d\" skipped=\"%d\" time=\"%.3f\">\n",
                self::escape($result->file),
                $result->countTests(),
                $result->countFailed(),
                $result->countSkipped(),
                $result->time,
            );
            foreach ($result->tests as $test) {
                $failure = $test->failure ?? ($test->subtestFailed ? new Failure(['message' => 'a subtest failed']) : null);
                $xml .= self::renderTestCase($result->file, $test->title, $test->time, $failure, $test->skipped);
            }
            $xml .= "  </testsuite>\n";
        }
        return $xml . "</testsuites>\n";
    }

    private static function renderTestCase(string $file, string $name, float $time, ?Failure $failure, ?string $skipped = null): string
    {
        $open = sprintf('    <testcase classname="%s" name="%s" time="%.3f"', self::escape($file), self::escape($name), $time);
        if ($skipped !== null) {
            return $open . ">\n      <skipped message=\"" . self::escape($skipped) . "\"/>\n    </testcase>\n";
        }
        if ($failure === null) {
            return $open . "/>\n";
        }
        $location = $failure->formatLocation() ?? $file;
        $message = $failure->describe();
        return $open . ">\n      <failure message=\"" . self::escape(explode("\n", $message)[0]) . '">'
            . self::escape($location . "\n" . $message) . "</failure>\n    </testcase>\n";
    }

    private static function escape(string $text): string
    {
        $text = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', '', $text);
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
