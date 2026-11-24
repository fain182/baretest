<?php

declare(strict_types=1);

namespace Baretest\Core;

/** Two values that differ in a failed equal(), as the worker rendered them. */
class Comparison
{
    private string $path;
    private string $expected;
    private string $got;
    private string $expectedType;
    private string $gotType;
    private ?int $caret;

    /**
     * @param ?int $caret the byte of $got where it first differs from $expected, if the two can be compared that way
     * @param string $path the keys of the two values in the arrays given to equal(), or '' for those arrays themselves
     */
    public function __construct(string $expected, string $got, string $expectedType, string $gotType, ?int $caret, string $path = '')
    {
        $this->path = $path;
        $this->expected = $expected;
        $this->got = $got;
        $this->expectedType = $expectedType;
        $this->gotType = $gotType;
        $this->caret = $caret;
    }

    /**
     * @param mixed $expected
     * @param mixed $got
     * @return list<self> one for each element that differs, if both are arrays with the same keys in the same order; else one for the two values
     */
    public static function listDifferences($expected, $got, string $path = ''): array
    {
        if (!is_array($expected) || !is_array($got) || array_keys($expected) !== array_keys($got)) {
            return [new self(Literal::render($expected, $got), Literal::render($got, $expected), Literal::describeType($expected), Literal::describeType($got), Literal::findOffset($expected, $got), $path)];
        }
        $differences = [];
        foreach ($expected as $key => $item) {
            if ($item !== $got[$key]) {
                $differences = array_merge($differences, self::listDifferences($item, $got[$key], $path . '[' . Literal::render($key) . ']'));
            }
        }
        return $differences;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getExpected(): string
    {
        return $this->expected;
    }

    public function getGot(): string
    {
        return $this->got;
    }

    public function getExpectedType(): string
    {
        return $this->expectedType;
    }

    public function getGotType(): string
    {
        return $this->gotType;
    }

    public function getCaret(): ?int
    {
        return $this->caret;
    }

    public function describe(): string
    {
        return ($this->path === '' ? '' : $this->path . ': ') . sprintf('expected %s (%s), got %s (%s)', $this->expected, $this->expectedType, $this->got, $this->gotType);
    }
}
