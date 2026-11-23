<?php

use Baretest\Core\Execution;
use Baretest\Core\FileResult;
use Baretest\Core\Report;
use Baretest\Core\Suite;
use Baretest\Core\SuiteResult;
use Baretest\Core\TestFile;
use Baretest\Core\TestFileRepository;

use function Baretest\{test, equal, throws};

function repository(array $files): TestFileRepository
{
    return new class ($files) implements TestFileRepository {
        private array $files;

        public function __construct(array $paths)
        {
            $this->files = array_map(fn (string $path) => new TestFile($path), $paths);
        }

        public function listFiles(): array
        {
            return $this->files;
        }
    };
}

function execution(): Execution
{
    return new class () implements Execution {
        public function run(TestFile $file): FileResult
        {
            return new FileResult($file, [], 0.0, '');
        }
    };
}

/** A report that writes down what it is told. */
function journal(): Report
{
    return new class () implements Report {
        public array $entries = [];

        public function addFile(FileResult $file): void
        {
            $this->entries[] = $file->getFile()->getPath();
        }

        public function finish(SuiteResult $suite): void
        {
            $this->entries[] = 'finish ' . $suite->countFiles();
        }
    };
}

test('runs each file in order and tells every report', function () {
    $first = journal();
    $second = journal();
    (new Suite(repository(['a_test.php', 'b_test.php']), execution(), [$first, $second]))->run();
    equal(['a_test.php', 'b_test.php', 'finish 2'], $first->entries);
    equal($first->entries, $second->entries);
});

test('returns what happened to every file', function () {
    $suite = (new Suite(repository(['a_test.php']), execution(), []))->run();
    equal(['a_test.php'], array_map(fn (FileResult $file) => $file->getFile()->getPath(), $suite->listFiles()));
});

test('tells no report when the files cannot be listed', function () {
    $files = new class () implements TestFileRepository {
        public function listFiles(): array
        {
            throw new InvalidArgumentException('no such file or directory: nope');
        }
    };
    $report = journal();
    throws(InvalidArgumentException::class, fn () => (new Suite($files, execution(), [$report]))->run());
    equal([], $report->entries);
});
