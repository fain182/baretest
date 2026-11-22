<?php

declare(strict_types=1);

namespace Baretest\Worker;

/** Extends Error so that `catch (\Exception)` in a test does not swallow it. */
class Skip extends \Error
{
}
