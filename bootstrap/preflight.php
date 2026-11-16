<?php

/*
 * پیش‌بررسی نصب روی هاست. فقط مشکلاتی را می‌گیرد که قبل از بالا آمدن لاراول رخ می‌دهند
 * (و در حالت عادی فقط یک «500 Server Error» خالی نشان داده می‌شود).
 * کد ساده‌ی PHP است تا روی نسخه‌های قدیمی PHP هم اجرا شود.
 */

$root = dirname(__DIR__);
$problems = array();

if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    $problems[] = array(
        'نسخه‌ی PHP قدیمی است: '.PHP_VERSION,
        'این برنامه PHP نسخه‌ی 8.2 یا بالاتر می‌خواهد. در cPanel از «Select PHP Version» یا «MultiPHP Manager» نسخه‌ی 8.2 یا 8.3 را برای این دامنه انتخاب کنید.',
    );
}

foreach (array('pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'xml', 'dom', 'ctype', 'tokenizer') as $ext) {
    if (! extension_loaded($ext)) {
        $problems[] = array('افزونه‌ی PHP فعال نیست: '.$ext, 'از «Select PHP Version» ← Extensions آن را فعال کنید.');
    }
}

if (! is_file($root.'/vendor/autoload.php')) {
    $problems[] = array(
        'پوشه‌ی vendor وجود ندارد',
        'کتابخانه‌ها در گیت نیستند. یا روی هاست در پوشه‌ی پروژه «composer install --no-dev» را اجرا کنید، یا روی سیستم خودتان اجرا کنید و پوشه‌ی vendor را آپلود کنید.',
    );
}

$envFile = $root.'/.env';
if (! is_file($envFile)) {
    $problems[] = array(
        'فایل .env وجود ندارد',
        'فایل .env.example را کپی کنید و نامش را .env بگذارید، سپس اطلاعات دیتابیس (DB_*) و APP_URL را پر کنید.',
    );
} else {
    $env = (string) @file_get_contents($envFile);
    if (! preg_match('/^APP_KEY=\s*["\']?base64:[A-Za-z0-9+\/=]{40,}/m', $env)) {
        $problems[] = array(
            'APP_KEY در فایل .env خالی است',
            'دستور «php artisan key:generate» را اجرا کنید، یا یک کلید بسازید و در .env بنویسید: APP_KEY=base64:… (32 بایت تصادفی با base64).'
                .' نمونه‌ی آماده برای همین نصب: APP_KEY=base64:'.base64_encode(random_bytes(32)),
        );
    }
}

foreach (array('storage', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app', 'bootstrap/cache') as $dir) {
    $path = $root.'/'.$dir;
    if (! is_dir($path) && ! @mkdir($path, 0775, true)) {
        $problems[] = array('پوشه وجود ندارد: '.$dir, 'این پوشه را بسازید و دسترسی نوشتن (775) بدهید.');
    } elseif (! is_writable($path)) {
        $problems[] = array('پوشه قابل نوشتن نیست: '.$dir, 'از File Manager دسترسی این پوشه را 775 کنید (پوشه‌های storage و bootstrap/cache با زیرپوشه‌ها).');
    }
}

if ($problems) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<title>نصب کامل نیست</title><style>body{font:15px/1.8 Tahoma,sans-serif;background:#EFF4F9;color:#10202E;margin:0;padding:24px}'
        .'.b{max-width:720px;margin:0 auto;background:#fff;border-radius:16px;padding:24px;box-shadow:0 6px 24px rgba(0,0,0,.08)}'
        .'h1{color:#1777B0;font-size:20px;margin-top:0}li{margin-bottom:12px}b{color:#B91C1C}code{direction:ltr;display:inline-block}</style></head>'
        .'<body><div class="b"><h1>تله‌پنل: نصب روی این هاست کامل نیست</h1><ol>';
    foreach ($problems as $p) {
        echo '<li><b>'.htmlspecialchars($p[0], ENT_QUOTES, 'UTF-8').'</b><br>'.htmlspecialchars($p[1], ENT_QUOTES, 'UTF-8').'</li>';
    }
    echo '</ol><p>بعد از رفع موارد، صفحه را دوباره باز کنید. راهنمای کامل در README.md است.</p></div></body></html>';
    exit;
}

unset($root, $problems, $envFile, $env, $dir, $path, $ext);
