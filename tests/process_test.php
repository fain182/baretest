<?php

use Baretest\Runner\Process;
use function Baretest\{test, equal};

/** What PHP prints when it runs $code with $command. */
function runPhp(array $command, string $code): string
{
    $process = proc_open([...$command, '-r', $code], [1 => ['pipe', 'w']], $pipes);
    $output = stream_get_contents($pipes[1]);
    proc_close($process);
    return $output;
}

test('buildCommand', function () {
    ini_set('precision', '10');
    $command = Process::buildCommand();

    test('runs this PHP binary', fn () => equal(PHP_BINARY, $command[0]));
    test('loads the same php.ini, or none', fn () => equal(
        var_export(php_ini_loaded_file(), true),
        runPhp($command, 'var_export(php_ini_loaded_file());'),
    ));
    test('passes a setting changed in this process', fn () => equal('10', runPhp($command, 'echo ini_get("precision");')));
    test('gives PHP every setting of this process', fn () => equal(
        ini_get_all(null, false),
        unserialize(runPhp($command, 'echo serialize(ini_get_all(null, false));')),
    ));
});
