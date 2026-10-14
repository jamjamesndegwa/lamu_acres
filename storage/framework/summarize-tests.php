<?php

$path = $argv[1];
$raw = file_get_contents($path);
$start = strpos($raw, '{');
$json = json_decode(substr($raw, $start), true);

if (! is_array($json)) {
    echo substr($raw, 0, 1500);
    exit(1);
}

echo ($json['result'] ?? '?').' '.($json['passed'] ?? '?').'/'.($json['tests'] ?? '?').PHP_EOL;

foreach ($json['failures'] ?? [] as $failure) {
    echo $failure['test'].PHP_EOL;
    echo substr($failure['message'], 0, 600).PHP_EOL;
    echo '---'.PHP_EOL;
}
