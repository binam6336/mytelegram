<?php

namespace App\Http\Controllers;

use App\Support\PhpBinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * نصب از راه مرورگر: برای هاست‌هایی که ترمینال ندارند یا `php` خط فرمانشان نسخه‌ی دیگری
 * (مثلاً 7.4) است. migrate داخل همین PHP وب اجرا می‌شود.
 * اجرای migrate فقط وقتی مجاز است که migration اجرانشده وجود داشته باشد (بعد از نصب کاری نمی‌کند).
 */
class InstallController extends Controller
{
    public function show()
    {
        return view('install', $this->status());
    }

    public function migrate(Request $request)
    {
        $s = $this->status();
        if (! $s['db_ok']) {
            return back()->withErrors(['db' => 'اول اتصال دیتابیس را درست کنید.']);
        }
        if ($s['pending'] === 0) {
            return back()->with('ok', 'همه‌ی جدول‌ها از قبل ساخته شده‌اند.');
        }
        @set_time_limit(120);
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['db' => 'ساخت جدول‌ها ناموفق بود: '.mb_substr($e->getMessage(), 0, 300)]);
        }

        return back()->with('ok', 'جدول‌های دیتابیس ساخته شدند. حالا می‌توانید ثبت‌نام کنید.');
    }

    private function status(): array
    {
        $dbOk = false;
        $dbError = null;
        $pending = null;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
            $migrator = app('migrator');
            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
            $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
            $pending = count(array_diff($files, $ran));
        } catch (Throwable $e) {
            $dbError = mb_substr(preg_replace('/\(Connection:.*$/s', '', $e->getMessage()), 0, 250);
        }

        Cache::forget('php-binary:'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION);
        $cli = PhpBinary::find();
        $plain = PhpBinary::version('php');

        return [
            'web_version' => PHP_VERSION,
            'web_sapi' => PHP_SAPI,
            'cli' => $cli,
            'cli_version' => $cli ? PhpBinary::version($cli) : null,
            'plain_version' => $plain,
            'proc_open' => function_exists('proc_open'),
            'proxies' => array_map(fn ($p) => \App\Support\TelegramProxy::probe($p, 5), \App\Support\TelegramProxy::all()),
            'proxy_raw' => trim((string) config('telegram.proxy')) !== '',
            'db_ok' => $dbOk,
            'db_error' => $dbError,
            'pending' => $pending,
            'cron' => ($cli ?: '/path/to/php'.PHP_MAJOR_VERSION.PHP_MINOR_VERSION).' '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
        ];
    }
}
