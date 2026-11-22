<?php

use function Baretest\{test, equal, skip};

// Each directory in fixtures/ is run with the arguments in its `args` file (default: the
// directory itself); the whole output and the exit code must match expected.out and expected.code.
// SNAPSHOT_REPLACE=1 rewrites the expected files.

$root = dirname(__DIR__);

foreach (glob($root . '/fixtures/*', GLOB_ONLYDIR) as $dir) {
    test(basename($dir), function () use ($root, $dir) {
        if (basename($dir) === 'enum' && PHP_VERSION_ID < 80100) {
            skip('enums need PHP 8.1');
        }
        $name = 'fixtures/' . basename($dir);
        $args = is_file("$dir/args") ? preg_split('/\s+/', trim(file_get_contents("$dir/args"))) : [$name];
        $process = proc_open(
            [PHP_BINARY, "$root/bin/baretest", ...$args],
            [1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $pipes,
            $root,
            ['NO_COLOR' => '1'] + getenv(),
        );
        $output = stream_get_contents($pipes[1]);
        $code = proc_close($process);
        $output = preg_replace(['/\b\d+\.\d\ds\b/', '/time="\d+\.\d+"/'], ['0.00s', 'time="0.000"'], $output);

        if (getenv('SNAPSHOT_REPLACE')) {
            file_put_contents("$dir/expected.out", $output);
            file_put_contents("$dir/expected.code", "$code\n");
        }
        equal(file_get_contents("$dir/expected.out"), $output);
        equal((int) file_get_contents("$dir/expected.code"), $code);
    });
}
