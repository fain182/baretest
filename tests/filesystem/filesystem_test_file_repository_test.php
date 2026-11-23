<?php

use Baretest\Filesystem\FilesystemTestFileRepository;

use function Baretest\{test, equal, throws};

/** Runs $fn in a new temporary directory holding $files, which is the working directory meanwhile. */
function inProject(array $files, callable $fn): void
{
    $root = sys_get_temp_dir() . '/baretest-' . bin2hex(random_bytes(4));
    $cwd = getcwd();
    mkdir($root);
    try {
        foreach ($files as $file) {
            @mkdir(dirname("$root/$file"), 0777, true);
            touch("$root/$file");
        }
        chdir($root);
        $fn($root);
    } finally {
        chdir($cwd);
        exec('rm -rf ' . escapeshellarg($root));
    }
}

test('finds the test files under tests/ by default', function () {
    inProject(['tests/b_test.php', 'tests/sub/a_test.php', 'tests/helper.php', 'src/x_test.php'], function () {
        equal(['tests/b_test.php', 'tests/sub/a_test.php'], (new FilesystemTestFileRepository([]))->listFiles());
    });
});

test('takes a file as it is, and each file once', function () {
    inProject(['tests/a_test.php', 'tests/check.php'], function () {
        equal(['tests/check.php', 'tests/a_test.php'], (new FilesystemTestFileRepository(['tests/check.php', 'tests', 'tests/a_test.php']))->listFiles());
    });
});

test('lists paths relative to the working directory', function () {
    inProject(['tests/a_test.php'], function (string $root) {
        equal(['tests/a_test.php'], (new FilesystemTestFileRepository(["$root/tests"]))->listFiles());
    });
});

test('rejects a path that does not exist', function () {
    inProject([], function () {
        $e = throws(InvalidArgumentException::class, fn () => (new FilesystemTestFileRepository(['nope']))->listFiles());
        equal('no such file or directory: nope', $e->getMessage());
    });
});

test('rejects a broken symlink', function () {
    inProject(['tests/a_test.php'], function () {
        symlink('missing.php', 'tests/broken_test.php');
        $e = throws(InvalidArgumentException::class, fn () => (new FilesystemTestFileRepository(['tests']))->listFiles());
        equal('no such file or directory: tests/broken_test.php', $e->getMessage());
    });
});

test('rejects paths without test files', function () {
    inProject(['a/readme.md', 'b/helper.php'], function () {
        $e = throws(InvalidArgumentException::class, fn () => (new FilesystemTestFileRepository(['a', 'b']))->listFiles());
        equal('no *_test.php files in a, b', $e->getMessage());
    });
});
