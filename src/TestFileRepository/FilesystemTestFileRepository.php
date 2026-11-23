<?php

declare(strict_types=1);

namespace Baretest\TestFileRepository;

use Baretest\Core\TestFileRepository;

/** The given files, and the *_test.php files under the given directories, or under tests/. */
class FilesystemTestFileRepository implements TestFileRepository
{
    /** @var list<string> */
    private array $paths;

    /** @param list<string> $paths */
    public function __construct(array $paths)
    {
        $this->paths = $paths === [] ? ['tests'] : $paths;
    }

    public function listFiles(): array
    {
        $files = [];
        foreach ($this->paths as $path) {
            array_push($files, ...(is_dir($path) ? $this->search($path) : [$this->resolve($path)]));
        }
        if ($files === []) {
            throw new \InvalidArgumentException('no *_test.php files in ' . implode(', ', $this->paths));
        }
        return array_values(array_unique($files));
    }

    /** @return list<string> */
    private function search(string $directory): array
    {
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if (substr($file->getFilename(), -9) === '_test.php') {
                $files[] = $this->resolve($file->getPathname());
            }
        }
        sort($files);
        return $files;
    }

    /** The path relative to the working directory, once symlinks are followed. */
    private function resolve(string $path): string
    {
        $real = realpath($path);
        if ($real === false) {
            throw new \InvalidArgumentException("no such file or directory: $path");
        }
        $prefix = getcwd() . DIRECTORY_SEPARATOR;
        return strncmp($real, $prefix, strlen($prefix)) === 0 ? substr($real, strlen($prefix)) : $real;
    }
}
