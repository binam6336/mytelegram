<?php

namespace App\Services\Telegram;

use App\Models\TelegramAccount;
use danog\MadelineProto\API;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * پیاده‌سازی با MadelineProto (کلاینت MTProto نوشته‌شده با PHP).
 *
 * فقط در CLI استفاده می‌شود (php artisan telegram:exec / telegram:sync): در CLI خود
 * MadelineProto داخل همان پردازش اجرا می‌شود و با پایان دستور، نشست در فایل ذخیره
 * و اتصال بسته می‌شود — هیچ سرور یا اتصال ماندگاری روی هاست باقی نمی‌ماند.
 */
class MadelineGateway implements TelegramGateway
{
    /** @var array<int, API> */
    private array $apis = [];

    public function sendCode(TelegramAccount $account, string $phone): void
    {
        // هر ورود از نو با نشست تازه (نشست نیمه‌کاره‌ی قبلی بدون بارگذاری پاک می‌شود)
        if (! isset($this->apis[$account->id])) {
            $this->wipe($account);
        }
        $api = $this->api($account);
        $api->phoneLogin($phone);
    }

    public function verifyCode(TelegramAccount $account, string $code): array
    {
        $api = $this->api($account);
        $res = $api->completePhoneLogin($code);
        $type = is_array($res) ? ($res['_'] ?? '') : '';

        if ($type === 'account.password') {
            return ['status' => 'password_needed', 'hint' => (string) ($res['hint'] ?? '')];
        }
        if ($type === 'account.needSignup') {
            throw new RuntimeException('این شماره هنوز حساب تلگرام ندارد. ابتدا در اپلیکیشن تلگرام ثبت‌نام کنید.');
        }

        return ['status' => 'authorized', 'me' => $this->me($api)];
    }

    public function verifyPassword(TelegramAccount $account, string $password): array
    {
        $api = $this->api($account);
        $api->complete2faLogin($password);

        return ['me' => $this->me($api)];
    }

    public function fetchNew(TelegramAccount $account, array $cursors, int $since, ?int $lastSync, float $budget): array
    {
        $started = microtime(true);
        $left = fn () => $budget - (microtime(true) - $started);
        $api = $this->api($account);
        if ($api->getAuthorization() !== API::LOGGED_IN) {
            throw new RuntimeException('نشست تلگرام معتبر نیست؛ دوباره وارد شوید.');
        }

        $messages = [];
        $complete = true;
        $maxDialogs = (int) config('telegram.max_dialogs', 300);
        // گفتگوهایی که پیام آخرشان قبل از آخرین همگام‌سازی است تغییری ندارند (با ۲ دقیقه حاشیه)
        $stopAt = $lastSync ? $lastSync - 120 : $since - 120;

        $offset = ['offset_date' => 0, 'offset_id' => 0, 'offset_peer' => ['_' => 'inputPeerEmpty']];
        $seen = 0;

        while ($seen < $maxDialogs) {
            if ($left() < 2) {
                $complete = false;
                break;
            }
            $page = $api->messages->getDialogs(...$offset, limit: 100);
            $dialogs = $page['dialogs'] ?? [];
            if (! $dialogs) {
                break;
            }
            $tops = $this->indexMessages($page['messages'] ?? []);
            $names = $this->names($page);
            $older = false;
            $lastDialog = null;

            foreach ($dialogs as $dialog) {
                $seen++;
                if (($dialog['_'] ?? '') !== 'dialog') {
                    continue;
                }
                $peer = $this->peerId($dialog['peer']);
                $topId = (int) ($dialog['top_message'] ?? 0);
                $top = $tops[$peer.':'.$topId] ?? null;
                $topDate = (int) ($top['date'] ?? 0);
                $lastDialog = [$dialog, $top];

                if ($topDate && $topDate < $stopAt && empty($dialog['pinned'])) {
                    $older = true;
                }

                $cursor = $cursors[$peer] ?? null;
                if ($cursor === null) {
                    // گفتگویی که قبلاً ندیده‌ایم: فقط پیام‌های بعد از لحظه‌ی اتصال
                    if ($topDate < $since) {
                        $cursors[$peer] = $topId;
                        continue;
                    }
                    $cursor = 0;
                }
                if ($topId <= $cursor) {
                    continue;
                }
                if ($left() < 2) {
                    $complete = false;
                    break 2;
                }

                [$new, $maxId, $done] = $this->history($api, $peer, $cursor, $since, $names, $left);
                array_push($messages, ...$new);
                $cursors[$peer] = max($cursor, $maxId);
                if (! $done) {
                    $complete = false;
                }
            }

            if ($older || ($page['_'] ?? '') === 'messages.dialogs' || count($dialogs) < 100 || ! $lastDialog) {
                break;
            }
            [$d, $top] = $lastDialog;
            $offset = [
                'offset_date' => (int) ($top['date'] ?? 0),
                'offset_id' => (int) ($d['top_message'] ?? 0),
                'offset_peer' => (int) $this->peerId($d['peer']),
            ];
        }

        return ['messages' => $messages, 'cursors' => $cursors, 'complete' => $complete];
    }

    public function logout(TelegramAccount $account): void
    {
        $path = $account->sessionPath();
        if (File::exists($path)) {
            try {
                $api = $this->api($account);
                if ($api->getAuthorization() === API::LOGGED_IN) {
                    $api->logout();
                }
            } catch (\Throwable) {
                // اگر نشست خراب باشد فقط فایل‌ها حذف می‌شوند
            }
        }
        $this->wipe($account);
    }

    private function wipe(TelegramAccount $account): void
    {
        unset($this->apis[$account->id]);
        foreach (File::glob($account->sessionPath().'*') ?: [] as $f) {
            File::isDirectory($f) ? File::deleteDirectory($f) : File::delete($f);
        }
    }

    // ------------------------------------------------------------------

    /**
     * پیام‌های جدیدتر از مکان‌نما، از قدیم به جدید (offset_id + add_offset منفی)،
     * تا اگر زمان تمام شد مکان‌نما تا همان‌جا جلو برود و بقیه در دور بعد بیاید.
     */
    private function history(API $api, string|int $peer, int $cursor, int $since, array $names, callable $left): array
    {
        $out = [];
        $done = true;
        $limit = 100;

        if ($cursor === 0) {
            // گفتگوی تازه بعد از اتصال: آخرین ۱۰۰ پیام، فقط آن‌هایی که بعد از لحظه‌ی اتصال آمده‌اند
            $res = $api->messages->getHistory(
                peer: (int) $peer, offset_id: 0, offset_date: 0, add_offset: 0,
                limit: $limit, max_id: 0, min_id: 0,
            );
            $names = $names + $this->names($res);
            foreach ($res['messages'] ?? [] as $m) {
                $cursor = max($cursor, (int) ($m['id'] ?? 0));
                if (($m['_'] ?? '') === 'message' && (int) ($m['date'] ?? 0) >= $since) {
                    $out[] = $this->normalize($m, $peer, $names);
                }
            }

            return [$out, $cursor, true];
        }

        for ($i = 0; $i < 50; $i++) {
            $res = $api->messages->getHistory(
                peer: (int) $peer, offset_id: $cursor, offset_date: 0, add_offset: -$limit,
                limit: $limit, max_id: 0, min_id: $cursor,
            );
            $names = $names + $this->names($res);
            $batch = $res['messages'] ?? [];
            if (! $batch) {
                break;
            }
            $next = $cursor;
            foreach ($batch as $m) {
                $id = (int) ($m['id'] ?? 0);
                if ($id <= $cursor) {
                    continue;
                }
                $next = max($next, $id);
                if (($m['_'] ?? '') !== 'message' || (int) ($m['date'] ?? 0) < $since) {
                    continue;
                }
                $out[] = $this->normalize($m, $peer, $names);
            }
            if ($next === $cursor) {
                break;
            }
            $cursor = $next;
            if (count($batch) < $limit) {
                break;
            }
            if ($left() < 2) {
                $done = false;
                break;
            }
        }

        return [$out, $cursor, $done];
    }

    private function normalize(array $m, string|int $peer, array $names): array
    {
        $chat = $names[(string) $peer] ?? ['title' => null, 'type' => null];
        $out = ! empty($m['out']);
        $from = isset($m['from_id']) ? $this->peerId($m['from_id']) : null;
        if ($from === null && $chat['type'] === 'user' && ! $out) {
            $from = $peer;
        }
        $media = $m['media']['_'] ?? null;

        return [
            'chat_id' => (string) $peer,
            'chat_title' => $chat['title'],
            'chat_type' => $chat['type'],
            'message_id' => (int) $m['id'],
            'sender_id' => $from !== null ? (string) $from : null,
            'sender_name' => $out ? 'من' : ($from !== null ? ($names[(string) $from]['title'] ?? null) : $chat['title']),
            'is_outgoing' => $out,
            'text' => ($m['message'] ?? '') !== '' ? $m['message'] : null,
            'media_type' => $media ? preg_replace('/^messageMedia/', '', $media) : null,
            'sent_at' => (int) ($m['date'] ?? time()),
        ];
    }

    /** نام و نوع کاربران/گروه‌ها/کانال‌های داخل یک پاسخ، با کلید شناسه‌ی Bot-API */
    private function names(array $res): array
    {
        $map = [];
        foreach ($res['users'] ?? [] as $u) {
            $title = trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? ''));
            $map[(string) $u['id']] = [
                'title' => $title !== '' ? $title : ($u['username'] ?? (string) $u['id']),
                'type' => ! empty($u['bot']) ? 'bot' : 'user',
            ];
        }
        foreach ($res['chats'] ?? [] as $c) {
            $kind = $c['_'] ?? '';
            if (str_starts_with($kind, 'channel')) {
                $id = $this->channelId((int) $c['id']);
                $type = ! empty($c['megagroup']) ? 'supergroup' : 'channel';
            } else {
                $id = $this->chatId((int) $c['id']);
                $type = 'group';
            }
            $map[(string) $id] = ['title' => $c['title'] ?? null, 'type' => $type];
        }

        return $map;
    }

    private function indexMessages(array $messages): array
    {
        $idx = [];
        foreach ($messages as $m) {
            if (isset($m['peer_id'], $m['id'])) {
                $idx[$this->peerId($m['peer_id']).':'.$m['id']] = $m;
            }
        }

        return $idx;
    }

    /** شناسه‌ی همتا به فرم Bot-API (کاربر مثبت، گروه منفی، کانال -100…) */
    private function peerId(mixed $peer): string
    {
        if (is_int($peer) || is_string($peer)) {
            return (string) $peer;
        }

        return (string) match ($peer['_'] ?? '') {
            'peerUser' => (int) $peer['user_id'],
            'peerChat' => $this->chatId((int) $peer['chat_id']),
            'peerChannel' => $this->channelId((int) $peer['channel_id']),
            default => throw new RuntimeException('peer نامعتبر'),
        };
    }

    private function chatId(int $id): int
    {
        return $id > 0 ? -$id : $id;
    }

    private function channelId(int $id): int
    {
        return $id > 0 ? -(1000000000000 + $id) : $id;
    }

    private function me(API $api): array
    {
        $self = $api->getSelf();
        if (! is_array($self)) {
            return [];
        }

        return [
            'id' => (string) ($self['id'] ?? ''),
            'name' => trim(($self['first_name'] ?? '').' '.($self['last_name'] ?? '')),
            'username' => $self['username'] ?? null,
            'phone' => $self['phone'] ?? null,
        ];
    }

    private function api(TelegramAccount $account): API
    {
        if (isset($this->apis[$account->id])) {
            return $this->apis[$account->id];
        }
        if (! class_exists(API::class)) {
            throw new RuntimeException('کتابخانه‌ی MadelineProto نصب نیست (composer install).');
        }
        File::ensureDirectoryExists(dirname($account->sessionPath()), 0700);

        // بدون این ثابت MadelineProto یک «IPC server» جدا در پس‌زمینه می‌سازد که زنده می‌ماند.
        // با آن، نمونه‌ی کامل داخل همین پردازش اجرا می‌شود و با پایان پردازش، نشست ذخیره و
        // اتصال بسته می‌شود — همان چیزی که هاست اشتراکی لازم دارد (بدون اتصال ماندگار).
        if (! defined('MADELINE_WORKER_TYPE')) {
            define('MADELINE_WORKER_TYPE', 'madeline-ipc');
        }

        $settings = new Settings;
        $settings->getAppInfo()
            ->setApiId((int) $account->api_id)
            ->setApiHash($account->api_hash)
            ->setDeviceModel('Telegram Panel')
            ->setAppVersion('1.0');
        $settings->getLogger()
            ->setType(Logger::FILE_LOGGER)
            ->setExtra(storage_path('logs/madeline.log'))
            ->setLevel(Logger::LEVEL_ERROR)
            ->setMaxSize(5 * 1024 * 1024);

        return $this->apis[$account->id] = new API($account->sessionPath(), $settings);
    }
}
