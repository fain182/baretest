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
                "  <testsuite name=\"%s\" tests=\"%d\" failures=\"%d\" time=\"%.3f\">\n",
                self::escape($result->file),
                $result->countTests(),
                $result->countFailed(),
                $result->time,
            );
            foreach ($result->tests as $test) {
                $xml .= self::testCase($result->file, implode(' › ', $test['path']), $test['time'], $test['failure']);
            }
            if ($result->stopped !== null) {
                $xml .= self::testCase($result->file, $result->stopped['title'], 0.0, $result->stopped['failure']);
            }
            $xml .= "  </testsuite>\n";
        }
        return $xml . "</testsuites>\n";
    }

    private static function testCase(string $file, string $name, float $time, ?array $failure): string
    {
        $open = sprintf('    <testcase classname="%s" name="%s" time="%.3f"', self::escape($file), self::escape($name), $time);
        if ($failure === null) {
            return $open . "/>\n";
        }
        $location = isset($failure['file']) ? Report::relative($failure['file']) . (isset($failure['line']) ? ':' . $failure['line'] : '') : $file;
        $message = Report::describe($failure);
        return $open . ">\n      <failure message=\"" . self::escape(strtok($message, "\n")) . '">'
            . self::escape($location . "\n" . $message) . "</failure>\n    </testcase>\n";
    }

    private static function escape(string $text): string
    {
        $text = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', '', $text);
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
