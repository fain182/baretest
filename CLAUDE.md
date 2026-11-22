# baretest

A minimal test runner for PHP libraries. `README.md` is the contract: what it shows is what baretest does, and nothing more. Change the README first when behaviour changes, and judge every proposal against its Principles section.

## Constraints

- PHP 8.1 to 8.6, no Composer dependencies.
- No extensions. A PHP built with `--disable-all` has only `Core, date, pcre, hash, json, Reflection, standard, SPL`; use nothing else. Not even `mbstring`: values are shown and compared byte by byte, and the `^` marks a byte.
- `tokenizer` and `pcntl` are absent. `proc_open` exists but can be listed in `disable_functions`: then exit with code 2 and a message that says so.
- Source under 1,000 lines (README, principle 4). Check it with a `make` target that CI runs.

## Decisions the README does not explain

- One process per test file, never optional: a fatal error, `exit()` or segfault in a file must become that file's failure, and the other files still run.
- Expected warnings use `@` and `error_get_last()`. The error handler returns `false` when `!(error_reporting() & $level)`, so PHP records the suppressed warning; any other warning fails the test. Call `error_clear_last()` before each test and subtest. Checked on PHP 8.1 and 8.3.
- A failure stops its test by throwing. A test that wraps code in `catch (\Throwable)` would swallow it: decide how to make that visible before 1.0.
- The output in the README is the reference layout: labels right-aligned in one column, the type in parentheses after every value, the failing line as `14 │ code`, `✓`/`✗` everywhere, failures indented under their file.
- Differential testing (native vs polyfill outcomes) stays out of baretest; it lives in each polyfill's own tests.
- No speed claims in the README until a benchmark script in the repository measures them: 1 file with 1 test, 1 file with 10,000 table rows, 200 files with 10 tests, and a real suite.

## Open questions

- ASCII fallback for `✓ ✗ │` with `NO_COLOR` or `--ci`?
- A final summary listing the names of the failed tests?
- Which line `debug_backtrace()` reports for a call spread over several lines, on 8.1 and 8.6: verify before deciding which code to show.
- How long values are cut so that the `^` stays aligned.

## Testing baretest

Locally, `make test` on the host PHP. CI also runs the suite on the minimal PHP that users get: the `Dockerfile` built with `--disable-all` for each version from 8.1 to 8.6. Run fixture test files and compare the runner's whole output and exit code with the expected ones.

## First user

Once it runs, port `mbregex-polyfill`'s tests to it. That suite (over 600 table cases, expected warnings, invalid bytes) is the first check that the API holds; what does not fit is a design question, not something to patch around.
