<?php

/**
 * One-time: copy to public_html/index-debug.php, open https://lamuacres.co.ke/index-debug.php, then delete.
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

$appRoot = __DIR__.'/../lamu_acres';

try {
    if (! is_file($appRoot.'/vendor/autoload.php')) {
        throw new RuntimeException("Missing {$appRoot}/vendor/autoload.php — fix \$appRoot in index.php.");
    }

    define('LARAVEL_START', microtime(true));

    if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
        require $maintenance;
    }

    require $appRoot.'/vendor/autoload.php';

    /** @var \Illuminate\Foundation\Application $app */
    $app = require $appRoot.'/bootstrap/app.php';

    $app->handleRequest(\Illuminate\Http\Request::capture());
} catch (Throwable $exception) {
    echo "index.php would fail with:\n\n";
    echo $exception::class.': '.$exception->getMessage()."\n\n";
    echo $exception->getFile().':'.$exception->getLine()."\n";
}
