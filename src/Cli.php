<?php

declare(strict_types=1);

namespace Baretest;

require_once __DIR__ . '/Value.php';
require_once __DIR__ . '/FileResult.php';
require_once __DIR__ . '/Process.php';
require_once __DIR__ . '/Junit.php';
require_once __DIR__ . '/Report.php';

final class Cli
{
    private const USAGE = 'usage: baretest [-v] [--ci=github|gitlab] [PATH...]';

    /** @param list<string> $argv */
    public static function run(array $argv): int
    {
        try {
            [$verbose, $ci, $paths] = self::parse(array_slice($argv, 1));
            if (!function_exists('proc_open')) {
                throw new \RuntimeException('proc_open() is disabled in php.ini (disable_functions), and baretest runs each test file in its own process');
            }
            $files = self::find($paths === [] ? ['tests'] : $paths);

            $out = $ci === 'gitlab' ? STDERR : STDOUT;
            $noColor = getenv('NO_COLOR');
            $report = new Report($out, stream_isatty($out) && ($noColor === false || $noColor === ''), $verbose, $ci);
            foreach ($files as $file) {
                $report->add(Process::run($file, Report::makeRelative($file)));
            }
            $report->finish();
            return $report->getExitCode();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            fwrite(STDERR, 'baretest: ' . $e->getMessage() . "\n");
            return 2;
        }
    }

    /** @return array{bool, ?string, list<string>} */
    private static function parse(array $args): array
    {
        $verbose = false;
        $ci = null;
        $paths = [];
        foreach ($args as $arg) {
            if ($arg === '-v') {
                $verbose = true;
            } elseif (str_starts_with($arg, '--ci=')) {
                $ci = substr($arg, 5);
                if (!in_array($ci, ['github', 'gitlab'], true)) {
                    throw new \InvalidArgumentException("unknown --ci target \"$ci\"\n" . self::USAGE);
                }
            } elseif (str_starts_with($arg, '-')) {
                throw new \InvalidArgumentException("unknown option $arg\n" . self::USAGE);
            } else {
                $paths[] = $arg;
            }
        }
        return [$verbose, $ci, $paths];
    }

    /** @return list<string> absolute paths, each file once */
    private static function find(array $paths): array
    {
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = realpath($path);
            } elseif (is_dir($path)) {
                $found = [];
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (str_ends_with($file->getFilename(), '_test.php')) {
                        $found[] = realpath($file->getPathname());
                    }
                }
                sort($found);
                array_push($files, ...$found);
            } else {
                throw new \InvalidArgumentException("no such file or directory: $path");
            }
        }
        if ($files === []) {
            throw new \InvalidArgumentException('no *_test.php files in ' . implode(', ', $paths));
        }
        return array_values(array_unique($files));
    }
}
