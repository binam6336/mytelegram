<?php

use Illuminate\Support\Facades\Schedule;

// روی هاست اشتراکی فقط یک کران‌جاب لازم است:
//   * * * * * /usr/local/bin/php /home/USER/telegram-panel/artisan schedule:run >> /dev/null 2>&1
// هر دقیقه یک اجرای کوتاه (حداکثر ۴۵ ثانیه) و بعد پردازش تمام می‌شود؛ اتصال ماندگاری وجود ندارد.
Schedule::command('telegram:sync --budget=45')
    ->everyMinute()
    ->withoutOverlapping(2);
