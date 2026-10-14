<?php

$path = $argv[1];
$raw = file_get_contents($path);
$start = strpos($raw, '{');
$json = json_decode(substr($raw, $start), true);

if (! is_array($json)) {
    fwrite(STDERR, "Could not parse test output\n");
    exit(1);
}

echo ($json['result'] ?? 'unknown').' passed='.($json['passed'] ?? '?').' failed='.($json['failed'] ?? '?').PHP_EOL;

foreach ($json['failures'] ?? [] as $failure) {
    $message = strtok($failure['message'], "\n");
    echo $failure['test'].PHP_EOL.'  '.$message.PHP_EOL.PHP_EOL;
}
