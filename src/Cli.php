<?php

declare(strict_types=1);

namespace Baretest;

require_once __DIR__ . '/Value.php';
require_once __DIR__ . '/FileResult.php';
require_once __DIR__ . '/Process.php';
require_once __DIR__ . '/Github.php';
require_once __DIR__ . '/Junit.php';
require_once __DIR__ . '/Report.php';

final class Cli
{
    private const USAGE = 'usage: baretest [--ci=github|gitlab] [PATH...]';

    /** @param list<string> $argv */
    public static function run(array $argv): int
    {
        try {
            [$ci, $paths] = self::parse(array_slice($argv, 1));
            if (!function_exists('proc_open')) {
                throw new \RuntimeException('proc_open() is disabled in php.ini (disable_functions), and baretest runs each test file in its own process');
            }
            $files = self::find($paths === [] ? ['tests'] : $paths);

            $out = $ci === 'gitlab' ? STDERR : STDOUT;
            $noColor = getenv('NO_COLOR');
            $report = new Report($out, stream_isatty($out) && ($noColor === false || $noColor === ''));
            $results = [];
            foreach ($files as $file) {
                $results[] = $result = Process::run($file, FileResult::makeRelative($file));
                $report->add($result);
                if ($ci === 'github') {
                    fwrite($out, Github::render($result));
                }
            }
            $report->finish();
            if ($ci === 'gitlab') {
                fwrite(STDOUT, Junit::render($results));
            }
            return $report->getExitCode();
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            fwrite(STDERR, 'baretest: ' . $e->getMessage() . "\n");
            return 2;
        }
    }

    /** @return array{?string, list<string>} */
    private static function parse(array $args): array
    {
        $ci = null;
        $paths = [];
        foreach ($args as $arg) {
            if (strncmp($arg, '--ci=', 5) === 0) {
                $ci = substr($arg, 5);
                if (!in_array($ci, ['github', 'gitlab'], true)) {
                    throw new \InvalidArgumentException("unknown --ci target \"$ci\"\n" . self::USAGE);
                }
            } elseif (strncmp($arg, '-', 1) === 0) {
                throw new \InvalidArgumentException("unknown option $arg\n" . self::USAGE);
            } else {
                $paths[] = $arg;
            }
        }
        return [$ci, $paths];
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
                    if (substr($file->getFilename(), -9) === '_test.php') {
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
