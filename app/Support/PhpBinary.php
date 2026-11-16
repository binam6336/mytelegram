<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * پیدا کردن php-cli هم‌نسخه با PHP وب.
 *
 * روی هاست‌های cPanel/DirectAdmin دستور ساده‌ی `php` معمولاً نسخه‌ی پیش‌فرض سرور است
 * (مثلاً 7.4) و ربطی به نسخه‌ای که برای دامنه انتخاب شده (مثلاً 8.4) ندارد؛ PHP_BINARY در وب هم
 * اغلب php-fpm/lsphp است. اینجا مسیرهای رایج نسخه‌ی فعلی امتحان و با اجرای واقعی بررسی می‌شوند.
 */
class PhpBinary
{
    /** مسیر php-cli سازگار، یا null اگر پیدا نشد */
    public static function find(): ?string
    {
        $configured = config('telegram.php_binary');
        if ($configured) {
            return $configured;
        }
        $key = 'php-binary:'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached ?: null;
        }
        $found = null;
        foreach (self::candidates() as $path) {
            if (self::version($path) !== null && self::compatible(self::version($path))) {
                $found = $path;
                break;
            }
        }
        Cache::put($key, $found ?? '', now()->addHours($found ? 24 : 1));

        return $found;
    }

    /** نسخه‌ی یک فایل php (یا null اگر اجرا نشد) */
    public static function version(string $path): ?string
    {
        static $seen = [];
        if (array_key_exists($path, $seen)) {
            return $seen[$path];
        }
        if (! function_exists('proc_open') || ($path[0] === '/' && ! @is_executable($path))) {
            return $seen[$path] = null;
        }
        try {
            $p = new Process([$path, '-n', '-r', 'echo PHP_SAPI, " ", PHP_VERSION;'], null, null, null, 5);
            $p->run();
            $out = trim($p->getOutput());
            if ($p->isSuccessful() && preg_match('/^cli (\d+\.\d+\.\d+)/', $out, $m)) {
                return $seen[$path] = $m[1];
            }
        } catch (Throwable) {
        }

        return $seen[$path] = null;
    }

    /** همان نسخه‌ی major.minor وب، یا دست‌کم 8.2 */
    public static function compatible(string $version): bool
    {
        return version_compare($version, '8.2.0', '>=')
            && version_compare($version, PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.0', '>=')
            && version_compare($version, PHP_MAJOR_VERSION.'.'.(PHP_MINOR_VERSION + 1).'.0', '<');
    }

    /** @return list<string> */
    public static function candidates(): array
    {
        $mm = PHP_MAJOR_VERSION.PHP_MINOR_VERSION;           // 84
        $dot = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;      // 8.4
        $list = [];
        if (PHP_SAPI === 'cli' && PHP_BINARY) {
            $list[] = PHP_BINARY;
        }
        // کنار باینری وب (lsphp / php-fpm / php-cgi)
        if (PHP_BINARY) {
            $dir = dirname(PHP_BINARY);
            $list[] = $dir.'/php';
            $list[] = dirname($dir).'/bin/php';
            $list[] = $dir.DIRECTORY_SEPARATOR.'php.exe';
        }
        $list[] = PHP_BINDIR.'/php';
        array_push($list,
            "/opt/cpanel/ea-php{$mm}/root/usr/bin/php",      // cPanel EasyApache
            "/usr/local/bin/ea-php{$mm}",
            "/opt/alt/php{$mm}/usr/bin/php",                  // CloudLinux alt-php
            "/usr/local/php{$mm}/bin/php",                    // DirectAdmin
            "/usr/local/php{$dot}/bin/php",
            "/usr/bin/php{$dot}",                             // Debian/Ubuntu
            "/usr/bin/php{$mm}",
            "/usr/local/bin/php{$mm}",
            "/opt/plesk/php/{$dot}/bin/php",                  // Plesk
            "/usr/local/lsws/lsphp{$mm}/bin/php",             // LiteSpeed
        );
        $finder = (new PhpExecutableFinder)->find(false);
        if ($finder) {
            $list[] = $finder;
        }
        $list[] = '/usr/local/bin/php';
        $list[] = '/usr/bin/php';

        return array_values(array_unique(array_filter($list)));
    }
}
