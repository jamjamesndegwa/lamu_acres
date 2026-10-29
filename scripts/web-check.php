<?php

/**
 * Temporary production diagnostic. Copy to public_html, open once in the browser, then DELETE.
 *
 *   cp /home/lamuacresco/lamu_acres/scripts/web-check.php /home/lamuacresco/public_html/web-check.php
 *   https://lamuacres.co.ke/web-check.php?token=delete-after-use
 */

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

header('Content-Type: text/plain; charset=UTF-8');

$token = 'delete-after-use';

if (! hash_equals($token, (string) ($_GET['token'] ?? ''))) {
    http_response_code(404);
    exit('Not found');
}

$appRoot = realpath(__DIR__.'/../lamu_acres') ?: realpath(__DIR__.'/../../lamu_acres');

if ($appRoot === false) {
    exit("Could not find lamu_acres next to this script. Edit \$appRoot in web-check.php.\n");
}

$posixUser = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
    ? (posix_getpwuid(posix_geteuid())['name'] ?? '?')
    : '?';

echo "=== Lamu Acres web diagnostic ===\n\n";
echo 'PHP version: '.PHP_VERSION." (app requires ^8.3)\n";
echo 'Process user: '.$posixUser."\n";
echo 'open_basedir: '.(ini_get('open_basedir') ?: '(none)')."\n";
echo "App root: {$appRoot}\n\n";

$paths = [
    'vendor/autoload.php' => $appRoot.'/vendor/autoload.php',
    'bootstrap/app.php' => $appRoot.'/bootstrap/app.php',
    '.env' => $appRoot.'/.env',
    'config cache' => $appRoot.'/bootstrap/cache/config.php',
    'storage/logs' => $appRoot.'/storage/logs',
    'public/build/manifest.json' => $appRoot.'/public/build/manifest.json',
];

foreach ($paths as $label => $path) {
    $exists = file_exists($path);
    $readable = is_readable($path);
    $writable = is_writable($path);
    echo sprintf(
        "%-28s %s %s %s\n",
        $label.':',
        $exists ? 'exists' : 'MISSING',
        $readable ? 'readable' : 'NOT readable',
        $writable ? 'writable' : 'NOT writable'
    );
}

echo "\n--- Laravel bootstrap ---\n";

try {
    require $appRoot.'/vendor/autoload.php';
    /** @var Application $app */
    $app = require $appRoot.'/bootstrap/app.php';
    $console = $app->make(ConsoleKernel::class);
    $console->bootstrap();

    $key = (string) config('app.key');
    $keyCount = substr_count($key, 'base64:');

    echo 'APP_ENV: '.config('app.env')."\n";
    echo 'APP_DEBUG: '.(config('app.debug') ? 'true' : 'false')."\n";
    echo 'APP_KEY length: '.strlen($key)." (base64: occurrences: {$keyCount}, want 1)\n";
    echo 'DB connection: '.config('database.default')."\n";

    DB::connection()->getPdo();
    echo "DB PDO: OK\n";

    /** @var HttpKernel $http */
    $http = $app->make(HttpKernel::class);

    foreach (['/up', '/', '/land'] as $path) {
        $request = Request::create($path, 'GET');
        $response = $http->handle($request);
        echo $path.' HTTP status: '.$response->getStatusCode()."\n";
        if ($response->getStatusCode() >= 500) {
            echo substr($response->getContent(), 0, 500)."\n";
        }
        $http->terminate($request, $response);
    }
} catch (Throwable $exception) {
    echo "\nFAILED: ".$exception->getMessage()."\n";
    echo $exception->getFile().':'.$exception->getLine()."\n";
}

echo "\nDelete public_html/web-check.php when finished.\n";
