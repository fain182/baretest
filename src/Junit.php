<?php

declare(strict_types=1);

namespace Baretest;

/** The JUnit XML report that GitLab reads. */
final class Junit
{
    /** @param list<FileResult> $results */
    public static function render(array $results): string
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
                $failure = $test['failure'] ?? ($test['subtestFailed'] ? ['message' => 'a subtest failed'] : null);
                $xml .= self::renderTestCase($result->file, FileResult::formatTitle($test), $test['time'], $failure, $test['skipped']);
            }
            if ($result->stopped !== null) {
                $xml .= self::renderTestCase($result->file, $result->stopped['title'], 0.0, $result->stopped['failure']);
            }
            $xml .= "  </testsuite>\n";
        }
        return $xml . "</testsuites>\n";
    }

    private static function renderTestCase(string $file, string $name, float $time, ?array $failure, ?string $skipped = null): string
    {
        $open = sprintf('    <testcase classname="%s" name="%s" time="%.3f"', self::escape($file), self::escape($name), $time);
        if ($skipped !== null) {
            return $open . ">\n      <skipped message=\"" . self::escape($skipped) . "\"/>\n    </testcase>\n";
        }
        if ($failure === null) {
            return $open . "/>\n";
        }
        $location = Report::formatLocation($failure) ?? $file;
        $message = Report::describe($failure);
        return $open . ">\n      <failure message=\"" . self::escape(explode("\n", $message)[0]) . '">'
            . self::escape($location . "\n" . $message) . "</failure>\n    </testcase>\n";
    }

    private static function escape(string $text): string
    {
        $text = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', '', $text);
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
