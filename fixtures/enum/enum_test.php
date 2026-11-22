<?php

use function Baretest\{test, equal};

enum Suit { case Hearts; }

test('enum', fn () => equal(Suit::Hearts, 'Hearts'));
