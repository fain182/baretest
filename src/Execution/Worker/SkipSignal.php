<?php

declare(strict_types=1);

namespace Baretest\Execution\Worker;

/** Extends Error so that `catch (\Exception)` in a test does not swallow it. */
class SkipSignal extends \Error
{
}
