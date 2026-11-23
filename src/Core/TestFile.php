<?php

declare(strict_types=1);

namespace Baretest\Core;

/** A *_test.php file: the unit that runs on its own, since its tests are known only by running it. */
class TestFile
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    /** Relative to the working directory, when the file is under it. */
    public function getPath(): string
    {
        return $this->path;
    }
}
