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
