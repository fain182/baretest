<?php

declare(strict_types=1);

namespace Baretest\Runner;

/** The *_test.php files to run: the given files, and those found under the given directories, or under tests/. */
final class TestFiles
{
    /** @return list<string> absolute paths, each file once */
    public static function find(array $paths): array
    {
        $paths = $paths === [] ? ['tests'] : $paths;
        $files = [];
        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = realpath($path);
            } elseif (is_dir($path)) {
                $found = [];
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if (substr($file->getFilename(), -9) === '_test.php') {
                        $found[] = realpath($file->getPathname()) ?: self::reportMissing($file->getPathname());
                    }
                }
                sort($found);
                array_push($files, ...$found);
            } else {
                self::reportMissing($path);
            }
        }
        if ($files === []) {
            throw new \InvalidArgumentException('no *_test.php files in ' . implode(', ', $paths));
        }
        return array_values(array_unique($files));
    }

    /** @return never */
    private static function reportMissing(string $path)
    {
        throw new \InvalidArgumentException("no such file or directory: $path");
    }
}
