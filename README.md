# تله‌پنل — مدیریت پیام‌های تلگرام (Laravel)

پنل فارسی (راست‌به‌چپ) با لاراول ۱۲ و معماری MVC که به **کلاینت تلگرامِ خود کاربر** (حساب شخصی، نه ربات) وصل می‌شود،
پیام‌های رسیده را ذخیره می‌کند، با کلیدواژه فیلتر می‌کند و برای اجرا روی **هاست اشتراکی** طراحی شده است.

## قابلیت‌ها
- **ثبت‌نام/ورود** فقط با شماره‌ی موبایل و رمز عبور (محدودیت تلاش ناموفق: ۵ بار در ۵ دقیقه).
- **داشبورد آبی (رنگ تلگرام)** با آمار، آخرین پیام‌ها و **دکمه‌ی بزرگ سبز/قرمز روشن‌خاموش**.
- **صفحه‌ی تعاملی اتصال** (`/telegram`): ویزارد ۴ مرحله‌ای با هواپیمای متحرک
  ۱) `client_id` (api_id) و `client_hash` (api_hash) ← ۲) شماره‌ی تلگرام ← ۳) کد ورود (خانه‌های جدا، چسباندن کل کد، لرزش در کد اشتباه) ← ۴) رمز دومرحله‌ای (در صورت وجود) ← متصل.
- از **لحظه‌ی روشن کردن**، هر پیامی که به کلاینت برسد (خصوصی، گروه، سوپرگروه، کانال، ربات؛ ورودی و خروجی) ذخیره می‌شود. پیام‌های قبل از روشن شدن و زمانی که سیستم خاموش است وارد نمی‌شوند.
- **همه‌ی پیام‌ها** (`/messages`): جست‌وجو، فیلتر بر اساس گفتگو، صفحه‌بندی و دکمه‌ی **«حذف همه‌ی پیام‌ها (به‌جز فیلترشده‌ها)»** با تأیید تایپی.
- **فیلتر پیام** (`/filters`): تعریف کلیدواژه‌ها (تا ۲۰۰، چندتایی با ویرگول)، فیلتر خودکار پیام‌های تازه، اعمال کلیدواژه‌ی جدید روی پیام‌های قبلی، هایلایت کلمه‌ها و شمارش هر کلیدواژه.
  پیام‌های فیلترشده در **جدول جدای `filtered_messages`** (با کپی متن) ذخیره می‌شوند تا با حذف پیام‌ها از بین نروند.
  یکسان‌سازی فارسی: «ي/ی»، «ك/ک»، ارقام فارسی/عربی/انگلیسی، نیم‌فاصله و اعراب.

## چرا بدون اتصال ماندگار کار می‌کند؟
کلاینت‌های MTProto معمولاً یک پردازش همیشه‌روشن می‌خواهند که روی هاست اشتراکی ممکن نیست. اینجا:

1. هر عملیات (ورود، دریافت پیام) یک **اجرای کوتاه** است: اتصال باز می‌شود، کار انجام می‌شود، نشست در فایل
   (`storage/app/telegram/session-<id>.madeline`) ذخیره و پردازش تمام می‌شود.
2. MadelineProto به‌طور پیش‌فرض یک «IPC server» پس‌زمینه می‌سازد؛ اینجا با ثابت `MADELINE_WORKER_TYPE` نمونه‌ی کامل
   **داخل همان پردازش** اجرا می‌شود و هیچ پردازش زنده‌ای باقی نمی‌ماند (`app/Services/Telegram/MadelineGateway.php`).
3. به‌جای گوش دادن دائمی به آپدیت‌ها، هر دور فقط `messages.getDialogs` را می‌خواند و برای گفتگوهایی که پیام جدیدتری
   از مکان‌نمای ذخیره‌شده دارند (`telegram_chat_cursors`) با `messages.getHistory` فقط پیام‌های جدید را می‌گیرد؛
   اگر زمان دور تمام شود ادامه در دور بعد.
4. دو راه راه‌اندازی دورها (هر دو کوتاه):
   - **کران‌جاب هاست** هر دقیقه (`schedule:run` ← `telegram:sync`، حداکثر ۴۵ ثانیه).
   - **وقتی پنل باز است** مرورگر هر ۳۰ ثانیه `POST /telegram/sync` می‌زند (حداکثر ۲۰ ثانیه، با قفل هر حساب).

## نصب روی هاست اشتراکی (cPanel/DirectAdmin)
پیش‌نیاز: PHP **8.2+** (CLI و وب) با `pdo_mysql`، `mbstring`، `openssl`، `fileinfo`، `xml`، `json`، `zlib`؛ MySQL/MariaDB؛
امکان کران‌جاب؛ برای حالت پیش‌فرض، `proc_open` فعال (اگر نبود `TELEGRAM_MODE=inline`). هاست باید به سرورهای تلگرام دسترسی داشته باشد
(هاست‌های داخل ایران معمولاً ندارند؛ هاست خارج از ایران لازم است).

1. پوشه‌ی `vendor` (بدون پکیج‌های توسعه) داخل مخزن است؛ کافی است کل پروژه را آپلود کنید. Composer لازم نیست.
   اگر وابستگی‌ها را عوض کردید: `composer update --no-dev` و دوباره کامیت `vendor` (`config.platform.php` روی 8.2.12 قفل است).
2. Document Root دامنه را روی پوشه‌ی `public` بگذارید. اگر ممکن نیست، فایل `.htaccess` ریشه‌ی پروژه درخواست‌ها را به `public/` می‌فرستد.
3. `cp .env.example .env`، مقادیر `APP_URL` و `DB_*` را پر کنید، `APP_DEBUG=false` و `APP_ENV=production`، سپس:
   ```
   php artisan key:generate
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
   (اگر ترمینال ندارید این دستورها را از بخش «Cron Jobs» یک بار اجرا کنید.)
4. پوشه‌های `storage` و `bootstrap/cache` قابل نوشتن باشند.
5. کران‌جاب (هر دقیقه):
   ```
   * * * * * /usr/local/bin/php /home/USER/telegram-panel/artisan schedule:run >> /dev/null 2>&1
   ```
   مسیر php-cli را از پشتیبانی هاست بپرسید و همان را در `TELEGRAM_PHP_BINARY` هم بگذارید
   (در درخواست وب `PHP_BINARY` گاهی php-fpm است).
6. ثبت‌نام کنید، از [my.telegram.org](https://my.telegram.org) ← API development tools یک اپ بسازید و api_id/api_hash را در صفحه‌ی اتصال وارد کنید.

### تنظیمات `.env`
| کلید | پیش‌فرض | توضیح |
|---|---|---|
| `TELEGRAM_MODE` | `process` | `process` = پردازش کوتاه CLI برای هر عملیات؛ `inline` = داخل همان درخواست وب |
| `TELEGRAM_PHP_BINARY` | خودکار | مسیر php-cli |
| `TELEGRAM_SYNC_BUDGET` | `20` | حداکثر ثانیه‌ی هر دور دریافت از مرورگر |
| `TELEGRAM_PROCESS_TIMEOUT` | `60` | سقف زمان پردازش CLI |
| `TELEGRAM_BROWSER_INTERVAL` | `30` | فاصله‌ی دریافت خودکار وقتی پنل باز است |
| `TELEGRAM_MAX_DIALOGS` | `300` | سقف گفتگوهای بررسی‌شده در هر دور |
| `TELEGRAM_FAKE` | `false` | فقط توسعه: درگاه آزمایشی بدون تلگرام (کد `12345`، کد `22222` = رمز دومرحله‌ای `secret`) |

## ساختار (MVC)
```
app/Http/Controllers   AuthController, DashboardController, TelegramController, MessageController, FilterController
app/Models             User, TelegramAccount, TelegramChatCursor, Message, Keyword, FilteredMessage
app/Services           KeywordFilter (تطبیق و جدول فیلترشده‌ها)
app/Services/Telegram  TelegramGateway (اینترفیس) ← MadelineGateway | FakeGateway
                       TelegramService (منطق ورود/همگام‌سازی، قفل هر حساب)
                       TelegramRunner (اجرای عملیات در پردازش کوتاه از وب)
app/Console/Commands   telegram:exec (یک عملیات؛ ورودی JSON از stdin)، telegram:sync (کران)
resources/views        Blade (layouts, dashboard, telegram/connect, messages, filters)
public/css/app.css, public/js/app.js   بدون مرحله‌ی build
```

### جدول‌ها
- `users` — phone (یکتا)، password.
- `telegram_accounts` — یک اتصال برای هر کاربر: api_id، api_hash (رمزنگاری‌شده با APP_KEY)، status (`new → code_sent → password_needed → authorized`)، `is_active` (روشن/خاموش)، `connected_at` (لحظه‌ی روشن شدن)، `last_synced_at`، `last_error`.
- `telegram_chat_cursors` — آخرین شناسه‌ی پیام ذخیره‌شده‌ی هر گفتگو.
- `messages` — پیام‌ها (یکتا روی حساب + گفتگو + شناسه‌ی پیام، پس دریافت تکراری ثبت نمی‌شود).
- `keywords` — کلیدواژه‌های هر کاربر.
- `filtered_messages` — کپی پیام‌های منطبق + `matched_keywords`؛ `message_id` با حذف پیام اصلی NULL می‌شود.

## توسعه‌ی محلی
```
cp .env.example .env   # DB_CONNECTION=sqlite و TELEGRAM_FAKE=true برای تست بدون تلگرام
php artisan key:generate && php artisan migrate   # vendor در مخزن هست؛ برای ابزارهای توسعه: composer install
php artisan serve
```
