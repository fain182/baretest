# baretest

A minimal test runner for PHP libraries. `README.md` is the contract: what it shows is what baretest does, and nothing more. Change the README first when behaviour changes, and judge every proposal against its Promises section.

## Constraints

- PHP 7.4 to 8.6, no Composer dependencies.
- No extensions. A PHP built with `--disable-all` has only `Core, date, pcre, hash, Reflection, standard, SPL`, and `json` only from PHP 8.0; use nothing else. Not even `mbstring`: values are shown and compared byte by byte, and the `^` marks a byte.
- `tokenizer` and `pcntl` are absent. `proc_open` exists but can be listed in `disable_functions`: then exit with code 2 and a message that says so.
- Source under 1,000 lines of code, counted as cloc counts them: no blank lines, no comments (README, promise 4). Check it with a `make` target that CI runs, without depending on cloc.

## Decisions the README does not explain

- One process per test file, never optional: a fatal error, `exit()` or segfault in a file must become that file's failure, and the other files still run.
- Expected warnings use `@` and `error_get_last()`. The error handler returns `false` when `!(error_reporting() & $level)`, so PHP records the suppressed warning; any other warning fails the test. Call `error_clear_last()` before each test and subtest. Checked on PHP 7.4, 8.0, 8.1 and 8.3.
- For a call spread over several lines, `debug_backtrace()` reports its last line on PHP 8.1 and its first from 8.2. The worker finds the whole call by counting parentheses from the function's name, so every version shows the same lines.
- A failure stops its test by throwing. A test that wraps code in `catch (\Throwable)` would swallow it: decide how to make that visible before 1.0.
- The expected output of the fixtures is the reference layout, and the README shows it only as a screenshot (`make screenshot`): labels right-aligned in one column, the type in parentheses after every value, the failing line as `14 │ code`, `✓`/`✗` everywhere, failures indented under their file, a `^` under the first differing element or byte, long values cut around it to 48 columns, a file's own output after `│`.
- No speed claims in the README until a benchmark script in the repository measures them: 1 file with 1 test, 1 file with 10,000 table rows, 200 files with 10 tests, and a real suite.

## Open questions


## Testing baretest

Locally, `make test` on the host PHP. CI also runs the suite on the minimal PHP that users get: the `Dockerfile` built with `--disable-all` for each version from 7.4 to 8.6. Run fixture test files and compare the runner's whole output and exit code with the expected ones.
