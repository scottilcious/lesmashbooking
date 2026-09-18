<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';
t_load_schema();

$files = glob(__DIR__ . '/*Test.php');
sort($files);
foreach ($files as $file) {
    if ($filter !== '' && stripos(basename($file), $filter) === false) {
        continue;
    }
    echo basename($file) . "\n";
    require $file;
}

exit(run_tests());
