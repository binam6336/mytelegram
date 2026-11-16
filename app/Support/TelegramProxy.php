<?php

namespace App\Support;

use danog\MadelineProto\Stream\MTProtoTransport\ObfuscatedStream;
use danog\MadelineProto\Stream\Proxy\HttpProxy;
use danog\MadelineProto\Stream\Proxy\SocksProxy;

/**
 * پروکسی اتصال به تلگرام (برای هاست‌های داخل ایران که تلگرام در آن‌ها فیلتر است).
 *
 * TELEGRAM_PROXY یک یا چند پروکسی است (با ویرگول یا فاصله جدا):
 *   tg://proxy?server=HOST&port=443&secret=ee…        لینک MTProxy (همان لینکی که در تلگرام می‌زنید)
 *   https://t.me/proxy?server=HOST&port=443&secret=…  همان، با t.me
 *   socks5://user:pass@HOST:1080                       SOCKS5
 *   http://user:pass@HOST:8080                         HTTP CONNECT
 * با چند پروکسی، MadelineProto اگر یکی جواب نداد سراغ بعدی می‌رود.
 */
class TelegramProxy
{
    /**
     * پروکسی‌ها برای MadelineProto: نام دامنه‌ی پروکسی همین‌جا با DNS خود هاست به IP تبدیل می‌شود،
     * چون MadelineProto به‌طور پیش‌فرض از DNS-over-HTTPS (Cloudflare/Google) استفاده می‌کند که از
     * هاست‌های داخل ایران معمولاً در دسترس نیست.
     */
    public static function forClient(): array
    {
        $out = [];
        foreach (self::all() as $p) {
            $ip = self::resolve($p['extra']['address']);
            if ($ip) {
                $p['extra']['address'] = $ip;
            }
            $out[$p['class']][] = $p['extra'];
        }

        return $out;
    }

    /** IP یک نام دامنه با DNS سیستم (یا خودش اگر IP است)؛ null اگر پیدا نشد */
    public static function resolve(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $ip = @gethostbyname($host);

        return $ip && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    /**
     * بررسی ساده‌ی دسترسی هاست به پروکسی (DNS + اتصال TCP)، بدون پروتکل تلگرام.
     *
     * @return array{label: string, host: string, port: int, ip: ?string, ok: bool, ms: ?int, error: ?string}
     */
    public static function probe(array $p, float $timeout = 6): array
    {
        $host = $p['extra']['address'];
        $port = (int) $p['extra']['port'];
        $r = ['label' => $p['label'], 'host' => $host, 'port' => $port, 'ip' => null, 'ok' => false, 'ms' => null, 'error' => null];
        $ip = self::resolve($host);
        if (! $ip) {
            $r['error'] = 'نام «'.$host.'» روی این هاست به IP تبدیل نشد (DNS).';

            return $r;
        }
        $r['ip'] = $ip;
        $t = microtime(true);
        $target = (str_contains($ip, ':') ? '['.$ip.']' : $ip).':'.$port;
        $sock = @stream_socket_client('tcp://'.$target, $errno, $errstr, $timeout);
        if (! $sock) {
            $r['error'] = 'هاست نتوانست به '.$ip.':'.$port.' وصل شود'.($errstr ? ' ('.trim($errstr).')' : '')
                .' — احتمالاً فایروال هاست اتصال خروجی به پورت '.$port.' را بسته یا پروکسی خاموش است.';

            return $r;
        }
        fclose($sock);
        $r['ok'] = true;
        $r['ms'] = (int) round((microtime(true) - $t) * 1000);

        return $r;
    }

    /** @return list<array{type: string, class: class-string, extra: array, label: string}> */
    public static function all(): array
    {
        $raw = trim((string) config('telegram.proxy'));
        if ($raw === '') {
            return [];
        }
        $out = [];
        foreach (preg_split('/[\s,]+/', $raw) as $item) {
            if ($item !== '' && ($p = self::parse($item))) {
                $out[] = $p;
            }
        }

        return $out;
    }

    public static function parse(string $url): ?array
    {
        $url = html_entity_decode(trim($url));
        if (preg_match('#^(tg://proxy|https?://t\.me/proxy)\?#i', $url)) {
            parse_str((string) parse_url(str_replace('tg://proxy', 'tg://x/proxy', $url), PHP_URL_QUERY), $q);
            if (empty($q['server']) || empty($q['port']) || empty($q['secret'])) {
                return null;
            }

            return [
                'type' => 'mtproxy',
                'class' => ObfuscatedStream::class,
                'extra' => ['address' => $q['server'], 'port' => (int) $q['port'], 'secret' => $q['secret']],
                'label' => 'MTProxy '.$q['server'].':'.$q['port'],
            ];
        }
        $p = parse_url($url);
        if (! $p || empty($p['host']) || empty($p['port']) || empty($p['scheme'])) {
            return null;
        }
        $scheme = strtolower($p['scheme']);
        $class = match ($scheme) {
            'socks5', 'socks5h', 'socks' => SocksProxy::class,
            'http', 'https' => HttpProxy::class,
            default => null,
        };
        if (! $class) {
            return null;
        }
        $extra = ['address' => $p['host'], 'port' => (int) $p['port']];
        if (isset($p['user'], $p['pass'])) {
            $extra['username'] = urldecode($p['user']);
            $extra['password'] = urldecode($p['pass']);
        }

        return [
            'type' => $class === SocksProxy::class ? 'socks5' : 'http',
            'class' => $class,
            'extra' => $extra,
            'label' => strtoupper($scheme).' '.$p['host'].':'.$p['port'],
        ];
    }
}
