<?php

declare(strict_types=1);

namespace Baretest\Output;

use Baretest\Result\FileResult;

/** The workflow commands that GitHub Actions shows as annotations on the failing lines. */
final class Github
{
    public static function annotate(FileResult $result): void
    {
        if (getenv('GITHUB_ACTIONS') === 'true') {
            fwrite(STDOUT, self::render($result));
        }
    }

    private static function render(FileResult $result): string
    {
        $out = '';
        foreach ($result->listFailures() as $test) {
            $failure = $test->failure;
            $file = $failure->file !== null ? FileResult::makeRelative($failure->file) : $result->file;
            $properties = 'file=' . self::escape($file, true)
                . ($failure->line !== null ? ',line=' . $failure->line : '')
                . ',title=' . self::escape($test->title, true);
            $out .= '::error ' . $properties . '::' . self::escape($failure->describe(), false) . "\n";
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
