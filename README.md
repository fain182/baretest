# baretest

A small test runner for PHP. No dependencies, no extensions, PHP 8.1 to 8.6.

You can learn all of it in five minutes and read its source in an afternoon.

## Why

PHPUnit and Pest do far more than baretest, and for an application they are the right choice. A library has different needs: it supports many PHP versions, it may have to run where few extensions are installed, and its tests should be as easy to read as its code.

| | baretest | PHPUnit 13 | Pest 5 |
|---|---|---|---|
| PHP versions | 8.1 – 8.6 | 8.4.1 and later | 8.4 and later |
| API to learn | 4 functions | 176 assertion methods | 16 functions and 131 expectations |
| Extensions required | none | dom, filter, json, libxml, mbstring, xmlwriter | those of PHPUnit, which it is built on |
| Test files isolated from each other | always, one process per file | opt-in, one process per test | not supported |
| Packages installed | 1 | 28 | 63 |
| Lines of PHP installed | under 1,000 | about 175,000 | about 333,000 |

<sub>Measured in November 2026 with `composer require` of the latest releases. baretest's size is a goal it holds itself to, not yet a measurement.</sub>

Each test file runs in its own PHP process. A fatal error, an `exit()` or a crash in one file is reported as that file's failure, and the other files still run.

baretest is the test runner for libraries. If you are not writing one, use PHPUnit or Pest.

## Principles

These are how baretest is developed and how proposals are judged.

1. **Runs wherever PHP runs.** No dependencies, no extensions, every supported PHP version. A feature that needs anything more is out.
2. **One way to do each thing.** No configuration, two flags, no option that changes what a test means.
3. **The README is the documentation.** A PHP programmer reads it and knows everything. If a feature cannot be explained here in a few lines, it does not belong in baretest.
4. **Small on purpose.** Under 1,000 lines, so that anyone who depends on it can read the whole source in an afternoon and trust it.
5. **Simple is not harsh.** Lines of code spent on a clearer message or more precise feedback are well spent, even against principle 4.
6. **Change needs a strong reason.** baretest is close to its final shape. A change must solve a real problem that cannot be solved in the test itself, and fit the principles above.

## Install

```
composer require --dev fain182/baretest
```

It needs nothing beyond what every PHP build has: no `dom`, `tokenizer`, `xml` or other extensions. It also runs on a PHP built with `--disable-all`.

## A test

A test file ends in `_test.php` and calls `test()` once for each test:

```php
<?php // tests/slug_test.php

use function Baretest\{test, equal, throws};

test('lowercases', function () {
    equal('hello-world', slug('Hello World'));
});

test('rejects empty input', function () {
    throws(InvalidArgumentException::class, fn () => slug(''));
});
```

That is the whole API:

| | |
|---|---|
| `test($name, $fn)` | runs `$fn` as a test; inside another test, as a subtest |
| `equal($expected, $actual)` | fails the test unless the two are identical (`===`) |
| `throws($class, $fn)` | fails the test unless `$fn` throws `$class`; returns the exception |
| `fail($message)` | fails the test |

A failure stops the test it happens in. Any other test, and any other subtest, still runs.

A warning, notice or deprecation fails the test, unless it was silenced with `@`. So does an uncaught exception.

## Table tests

A table is a `foreach`. Call `test()` for each row, so a failure says which row failed:

```php
test('splits', function () {
    $cases = [
        'commas'      => [',', 'a,b', ['a', 'b']],
        'no match'    => [',', 'abc', ['abc']],
        'empty input' => [',', '',    ['']],
    ];
    foreach ($cases as $name => [$separator, $input, $expected]) {
        test($name, fn () => equal($expected, explode($separator, $input)));
    }
});
```

## Expected warnings

Silence the call with `@` and read the warning with `error_get_last()`:

```php
test('rejects odd-length input', function () {
    $result = @hex2bin('abc');
    equal(false, $result);
    equal('hex2bin(): Hexadecimal input string must have an even length', error_get_last()['message']);
});
```

`error_get_last()` is cleared before each test and subtest. Within one test it keeps the last warning until another one replaces it, so call `error_clear_last()` between two checks.

## Everything else is PHP

Shared setup is a function. A repeated check is a function. A test that needs a temporary file creates it and deletes it in `finally`. baretest has no hooks for any of this, because PHP already does it.

## Running

```
vendor/bin/baretest [-v] [--ci=github|gitlab] [PATH...]
```

- `PATH` is a file or a directory; directories are searched for `*_test.php`. Default: `tests/`.
- Each file runs in its own PHP process, so global state does not leak between files.
- `-v` also lists the tests that pass.
- Output is coloured when it goes to a terminal, unless `NO_COLOR` is set.
- `--ci=github` adds an `::error` line for each failure, which GitHub Actions shows as an annotation on the code.
- `--ci=gitlab` writes a JUnit XML report to stdout and the usual output to stderr, so the job log stays readable: `vendor/bin/baretest --ci=gitlab > report.xml`, then point `artifacts:reports:junit` at `report.xml` with `artifacts:when: always`.

Exit code: `0` all passed, `1` some failed, `2` the runner could not run (bad arguments, a file that does not return tests).

## Output

```
✓ tests/encoding_test.php  38 tests  0.05s
✗ tests/split_test.php  3 of 41 failed  0.07s

  ✗ splits › trailing comma  (tests/split_test.php:14)
       14 │ test($name, fn () => equal($expected, explode($separator, $input)));

       expected  ["a", "b"]        (array)
            got  ["a", "b", ""]    (array)
                            ^

  ✗ splits › invalid bytes  (tests/split_test.php:14)
       14 │ test($name, fn () => equal($expected, explode($separator, $input)));

       expected  "b\xE0c"        (string)
            got  "b\xE0\xA0c"    (string)
                       ^

  ✗ counts pieces  (tests/split_test.php:30)
       30 │ equal(3, count_pieces('a,b,c'));

       expected  3      (int)
            got  "3"    (string)

✗ 3 of 214 tests failed in 2 of 9 files  0.41s
```

- One line per file, `✓` or `✗`, so you see progress. `-v` adds one line per test.
- Each failure sits under its file and starts with the test name and its `file:line`. Below it, the line that failed with its number, what was expected and what was got.
- Values are written as PHP would, followed by their type: strings in double quotes with `\n`, `\t` and `\xE0` for invisible characters and invalid bytes.
- A `^` marks the first element or byte that differs. Long values are cut around it.
- An unexpected warning or exception shows its level or class, its message and the `file:line` where it was raised.

## Not included, on purpose

- assertions beyond `equal` and `throws`: write the check in PHP and call `fail()`
- mocks, stubs and spies
- fixtures, setup/teardown hooks, dependency injection: use a function
- code coverage
- configuration files
- plugins, custom reporters, other output formats: a new `--ci` target is for a new platform, not a new style
- watch mode
- running files in parallel
- stopping at the first failure

A request for any of these will be closed with a link to this section.
