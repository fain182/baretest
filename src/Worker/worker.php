<?php

declare(strict_types=1);

// Runs one test file: php worker.php TEST_FILE RECORDS_FILE AUTOLOAD_FILE
require $argv[3];

Baretest\Worker\Session::runFile($argv[1], $argv[2]);
