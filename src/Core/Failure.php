<?php

declare(strict_types=1);

namespace Baretest\Core;

/** Why a test failed. */
class Failure
{
    private string $message;
    private ?string $label;

    public function __construct(string $message, ?string $label = null)
    {
        $this->message = $message;
        $this->label = $label;
    }

    public function describe(): string
    {
        if ($this->label === null) {
            return $this->message;
        }
        return $this->message === '' ? $this->label : $this->label . ': ' . $this->message;
    }
}
