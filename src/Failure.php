<?php

declare(strict_types=1);

namespace Baretest;

/** Extends Error so that `catch (\Exception)` in a test does not swallow it. */
final class Failure extends \Error
{
}
