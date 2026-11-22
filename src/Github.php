<?php

declare(strict_types=1);

namespace Baretest;

/** The workflow commands that GitHub Actions shows as annotations on the failing lines. */
final class Github
{
    public static function render(FileResult $result): string
    {
        $out = '';
        foreach ($result->listFailures() as $entry) {
            $failure = $entry['failure'];
            $file = isset($failure['file']) ? FileResult::makeRelative($failure['file']) : $result->file;
            $properties = 'file=' . self::escape($file, true)
                . (isset($failure['line']) ? ',line=' . $failure['line'] : '')
                . ',title=' . self::escape($entry['title'], true);
            $out .= '::error ' . $properties . '::' . self::escape(FileResult::describe($failure), false) . "\n";
        }
        return $out;
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
}
