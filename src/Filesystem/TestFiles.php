<?php

declare(strict_types=1);

namespace Baretest\Filesystem;

use Baretest\Core\TestFileRepository;

/** The *_test.php files under the given paths, or under tests/. */
class TestFiles implements TestFileRepository
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
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (substr($file->getFilename(), -9) === '_test.php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        return $files;
    }
}
