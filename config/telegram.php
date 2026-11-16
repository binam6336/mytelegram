<?php

return [
    /*
    | نحوه‌ی اجرای عملیات تلگرام:
    |  process: هر عملیات در یک پردازش کوتاه CLI (php artisan telegram:exec) اجرا و تمام می‌شود.
    |          پیشنهادی برای هاست اشتراکی؛ اتصال ماندگار و سرور پس‌زمینه ندارد.
    |  inline : داخل همان درخواست وب (اگر proc_open روی هاست بسته باشد).
    */
    'mode' => env('TELEGRAM_MODE', 'process'),

    // فقط برای توسعه/تست: درگاه آزمایشی بدون تلگرام واقعی (کد 12345)
    'fake' => (bool) env('TELEGRAM_FAKE', false),

    // مسیر php برای CLI روی هاست (مثلاً /usr/local/bin/php یا /opt/cpanel/ea-php83/root/usr/bin/php)
    'php_binary' => env('TELEGRAM_PHP_BINARY'),

    // حداکثر زمان هر دور همگام‌سازی (ثانیه) — کمتر از max_execution_time هاست
    'sync_budget' => (int) env('TELEGRAM_SYNC_BUDGET', 20),

    // سقف زمان کل اجرای یک پردازش CLI
    'process_timeout' => (int) env('TELEGRAM_PROCESS_TIMEOUT', 60),

    // فاصله‌ی همگام‌سازی خودکار از مرورگر وقتی داشبورد باز است (ثانیه)
    'browser_interval' => (int) env('TELEGRAM_BROWSER_INTERVAL', 30),

    // حداکثر تعداد گفتگوهایی که در هر دور بررسی می‌شوند
    'max_dialogs' => (int) env('TELEGRAM_MAX_DIALOGS', 300),
];
