<?php

declare(strict_types=1);

// Runs one test file: php worker.php TEST_FILE RECORDS_FILE
require __DIR__ . '/functions.php';

Baretest\Session::runFile($argv[1], $argv[2]);
