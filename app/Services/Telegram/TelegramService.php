<?php

namespace App\Services\Telegram;

use App\Models\Message;
use App\Models\TelegramAccount;
use App\Models\TelegramChatCursor;
use App\Services\KeywordFilter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * کار اصلی هر عملیات تلگرام (ورود، همگام‌سازی، خروج). همیشه داخل یک پردازش کوتاه اجرا می‌شود.
 * خروجی همه‌ی متدها: ['ok' => bool, 'message' => string, ...]
 */
class TelegramService
{
    public function __construct(private TelegramGateway $gateway, private KeywordFilter $filter) {}

    public static function pingKey(int $accountId): string
    {
        return 'tg-ping-state-'.$accountId;
    }

    public function run(TelegramAccount $account, string $action, array $payload = []): array
    {
        // روی هر حساب در هر لحظه فقط یک پردازش با فایل نشست کار می‌کند
        $lock = Cache::lock('tg-account-'.$account->id, (int) config('telegram.process_timeout', 60) + 10);
        if (! $lock->get()) {
            return ['ok' => false, 'busy' => true, 'message' => 'عملیات دیگری روی این حساب در حال اجراست؛ چند ثانیه بعد دوباره تلاش کنید.'];
        }
        try {
            return match ($action) {
                'send-code' => $this->sendCode($account, (string) ($payload['phone'] ?? '')),
                'verify-code' => $this->verifyCode($account, (string) ($payload['code'] ?? '')),
                'verify-password' => $this->verifyPassword($account, (string) ($payload['password'] ?? '')),
                'sync' => $this->sync($account, (float) ($payload['budget'] ?? config('telegram.sync_budget', 20))),
                'logout' => $this->logout($account),
                'ping' => ['ok' => true, 'message' => 'اتصال برقرار است.'] + $this->gateway->ping($account),
                default => ['ok' => false, 'message' => 'عملیات ناشناخته'],
            };
        } catch (Throwable $e) {
            Log::warning('telegram '.$action.' failed', ['account' => $account->id, 'error' => $e->getMessage()]);
            $msg = $this->humanError($e);
            if ($action === 'sync') {
                $account->update(['last_error' => $msg]);
                if (preg_match('/AUTH_KEY_UNREGISTERED|SESSION_REVOKED|USER_DEACTIVATED|نشست تلگرام معتبر نیست/', $e->getMessage())) {
                    // نشست از بین رفته: سیستم خاموش و کاربر به ورود دوباره هدایت می‌شود
                    $account->update(['status' => TelegramAccount::NEW, 'is_active' => false]);
                }
            }

            return ['ok' => false, 'message' => $msg];
        } finally {
            $lock->release();
        }
    }

    private function sendCode(TelegramAccount $account, string $phone): array
    {
        $this->gateway->sendCode($account, $phone);
        $account->update(['phone' => $phone, 'status' => TelegramAccount::CODE_SENT, 'last_error' => null]);

        return ['ok' => true, 'message' => 'کد ورود به تلگرام شما ارسال شد.'];
    }

    private function verifyCode(TelegramAccount $account, string $code): array
    {
        $res = $this->gateway->verifyCode($account, $code);
        if ($res['status'] === 'password_needed') {
            $account->update(['status' => TelegramAccount::PASSWORD_NEEDED, 'password_hint' => $res['hint'] ?: null]);

            return ['ok' => true, 'step' => 'password', 'message' => 'حساب شما رمز دومرحله‌ای دارد.'];
        }
        $this->authorized($account, $res['me'] ?? []);

        return ['ok' => true, 'step' => 'done', 'message' => 'اتصال به تلگرام برقرار شد.'];
    }

    private function verifyPassword(TelegramAccount $account, string $password): array
    {
        $res = $this->gateway->verifyPassword($account, $password);
        $this->authorized($account, $res['me'] ?? []);

        return ['ok' => true, 'step' => 'done', 'message' => 'اتصال به تلگرام برقرار شد.'];
    }

    private function authorized(TelegramAccount $account, array $me): void
    {
        $account->update([
            'status' => TelegramAccount::AUTHORIZED,
            'tg_user_id' => $me['id'] ?? null,
            'tg_name' => $me['name'] ?? null,
            'tg_username' => $me['username'] ?? null,
            'password_hint' => null,
            'last_error' => null,
        ]);
    }

    /** یک دور کوتاه همگام‌سازی: فقط پیام‌های جدید گفتگوهایی که تغییر کرده‌اند */
    public function sync(TelegramAccount $account, float $budget): array
    {
        if (! $account->isAuthorized() || ! $account->is_active) {
            return ['ok' => false, 'message' => 'سیستم خاموش است.'];
        }
        $since = ($account->connected_at ?? now())->getTimestamp();
        $cursors = $account->cursors()->pluck('last_message_id', 'peer_id')->map(fn ($v) => (int) $v)->all();

        $res = $this->gateway->fetchNew($account, $cursors, $since, $account->last_synced_at?->getTimestamp(), $budget);

        $saved = 0;
        $filtered = 0;
        DB::transaction(function () use ($account, $res, $cursors, &$saved, &$filtered) {
            $now = now();
            $rows = array_map(fn ($m) => [
                'user_id' => $account->user_id,
                'telegram_account_id' => $account->id,
                'chat_id' => $m['chat_id'],
                'chat_title' => mb_substr((string) $m['chat_title'], 0, 250) ?: null,
                'chat_type' => $m['chat_type'],
                'message_id' => $m['message_id'],
                'sender_id' => $m['sender_id'],
                'sender_name' => mb_substr((string) $m['sender_name'], 0, 250) ?: null,
                'is_outgoing' => $m['is_outgoing'],
                'text' => $m['text'],
                'media_type' => $m['media_type'],
                'sent_at' => Carbon::createFromTimestamp($m['sent_at'], config('app.timezone')),
                'created_at' => $now,
                'updated_at' => $now,
            ], $res['messages']);

            foreach (array_chunk($rows, 200) as $chunk) {
                $saved += Message::insertOrIgnore($chunk);
            }

            if ($rows) {
                // فیلتر خودکار پیام‌های تازه
                $keys = array_flip(array_map(fn ($r) => $r['chat_id'].':'.$r['message_id'], $rows));
                $fresh = Message::where('telegram_account_id', $account->id)
                    ->whereIn('message_id', array_column($rows, 'message_id'))
                    ->whereNotNull('text')->get()
                    ->filter(fn ($m) => isset($keys[$m->chat_id.':'.$m->message_id]));
                $filtered = $this->filter->apply($account->user_id, $fresh);
            }

            foreach ($res['cursors'] as $peer => $last) {
                if (($cursors[$peer] ?? null) !== (int) $last) {
                    TelegramChatCursor::updateOrCreate(
                        ['telegram_account_id' => $account->id, 'peer_id' => (string) $peer],
                        ['last_message_id' => (int) $last],
                    );
                }
            }

            // دور موفق یعنی اتصال برقرار است (چراغ سایدبار)
            Cache::put(self::pingKey($account->id), ['state' => 'ok', 'authorized' => true, 'ms' => null, 'at' => time()], 86400);

            $account->update([
                'last_synced_at' => $now,
                'last_error' => null,
                'synced_total' => $account->synced_total + $saved,
            ]);
        });

        return [
            'ok' => true,
            'saved' => $saved,
            'filtered' => $filtered,
            'complete' => $res['complete'],
            'message' => $saved ? $saved.' پیام جدید ذخیره شد.' : 'پیام جدیدی نبود.',
        ];
    }

    private function logout(TelegramAccount $account): array
    {
        $this->gateway->logout($account);
        $account->cursors()->delete();
        $account->update([
            'status' => TelegramAccount::NEW, 'is_active' => false, 'tg_user_id' => null,
            'tg_name' => null, 'tg_username' => null, 'password_hint' => null, 'connected_at' => null,
            'last_synced_at' => null, 'last_error' => null,
        ]);

        return ['ok' => true, 'message' => 'از حساب تلگرام خارج شدید.'];
    }

    private function humanError(Throwable $e): string
    {
        $m = $e->getMessage();
        $map = [
            'PHONE_CODE_INVALID' => 'کد واردشده اشتباه است.',
            'PHONE_CODE_EXPIRED' => 'کد منقضی شده؛ دوباره کد بگیرید.',
            'PHONE_NUMBER_INVALID' => 'شماره‌ی تلفن نامعتبر است (با کد کشور، مثل +98912…).',
            'PHONE_NUMBER_BANNED' => 'این شماره در تلگرام مسدود است.',
            'PASSWORD_HASH_INVALID' => 'رمز دومرحله‌ای اشتباه است.',
            'API_ID_INVALID' => 'api_id یا api_hash نامعتبر است.',
            'API_ID_PUBLISHED_FLOOD' => 'این api_id عمومی است و محدود شده؛ از my.telegram.org کلید شخصی بگیرید.',
            'AUTH_KEY_UNREGISTERED' => 'نشست تلگرام از بین رفته؛ دوباره وارد شوید.',
            'SESSION_REVOKED' => 'نشست از داخل تلگرام بسته شده؛ دوباره وارد شوید.',
            'FLOOD_WAIT' => 'تلگرام موقتاً درخواست‌ها را محدود کرده؛ کمی بعد تلاش کنید.',
            'SecurityException' => 'اتصال به سرورهای تلگرام برقرار نشد (شاید هاست دسترسی به تلگرام را بسته است).',
            'authorization key' => 'اتصال به سرورهای تلگرام برقرار نشد (شاید هاست دسترسی به تلگرام را بسته است).',
            'Could not connect' => 'اتصال به سرورهای تلگرام برقرار نشد (شاید هاست دسترسی به تلگرام را بسته است).',
        ];
        foreach ($map as $code => $text) {
            if (str_contains($m, $code)) {
                return $text;
            }
        }
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $m)) {
            return $m; // پیام فارسی خودمان
        }

        return 'خطا در ارتباط با تلگرام: '.mb_substr($m, 0, 160);
    }
}
