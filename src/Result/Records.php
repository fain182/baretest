<?php

declare(strict_types=1);

namespace Baretest\Result;

/** The file through which a worker tells the runner what happened, one record per line. */
class Records
{
    /** @var resource */
    private $handle;

    public function __construct(string $path)
    {
        $this->handle = fopen($path, 'ab');
    }

    public function write(array $record): void
    {
        fwrite($this->handle, base64_encode(serialize($record)) . "\n");
    }

    /** @return list<array> without the last line of a worker that died while writing it */
    public static function read(string $path): array
    {
        $decode = static fn (string $line) => @unserialize(base64_decode($line), ['allowed_classes' => false]);
        return array_values(array_filter(array_map($decode, file($path, FILE_IGNORE_NEW_LINES) ?: []), 'is_array'));
    }
}
