<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// پیش‌بررسی نصب: به‌جای خطای ۵۰۰ بی‌توضیح، علت را به فارسی نشان می‌دهد.
require __DIR__.'/../bootstrap/preflight.php';

// وقتی پروژه در یک زیرپوشه است (مثلاً public_html/mytelegram) و .htaccess ریشه درخواست‌ها را
// به public/ می‌فرستد، آدرس‌ها «/mytelegram/login» هستند ولی SCRIPT_NAME «/mytelegram/public/index.php» است
// و لاراول «/mytelegram» را جزء مسیر حساب می‌کند (404). اینجا ریشه‌ی آدرس را پوشه‌ی پروژه می‌گذاریم.
(function () {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $publicDir = rtrim(dirname($script), '/');
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (basename($script) === 'index.php' && str_ends_with($publicDir, '/public')
        && ! str_starts_with($uri.'/', $publicDir.'/')) {
        $base = substr($publicDir, 0, -strlen('/public'));
        $_SERVER['SCRIPT_NAME'] = $base.'/index.php';
        $_SERVER['PHP_SELF'] = $base.'/index.php';
    }
})();

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
