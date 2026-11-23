<?php

declare(strict_types=1);

namespace Baretest\Output;

use Baretest\Result\FileResult;

/** The workflow commands that GitHub Actions shows as annotations on the failing lines. */
class Github
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
            $failure = $test->getFailure();
            $properties = 'file=' . self::escape($failure->locateFile($result->getFile()), true)
                . ($failure->getLine() !== null ? ',line=' . $failure->getLine() : '')
                . ',title=' . self::escape($test->getTitle(), true);
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
