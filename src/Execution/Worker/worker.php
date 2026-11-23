<?php

declare(strict_types=1);

// php worker.php TEST_FILE RECORDS_FILE AUTOLOAD_FILE
require $argv[3];

Baretest\Execution\Worker\Session::runFile($argv[1], new Baretest\Execution\Records($argv[2]));
