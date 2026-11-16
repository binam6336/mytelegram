<?php

namespace App\Http\Controllers;

use App\Models\TelegramAccount;
use App\Services\Telegram\TelegramRunner;
use App\Services\Telegram\TelegramService;
use App\Support\TelegramProxy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TelegramController extends Controller
{
    public function __construct(private TelegramRunner $runner) {}

    public function show(Request $request)
    {
        $account = $request->user()->telegramAccount;

        return view('telegram.connect', [
            'account' => $account,
            'step' => $account ? $account->step() : 1,
            'phoneHint' => $account?->phone ?: self::intl($request->user()->phone),
        ]);
    }

    /** مرحله‌ی ۱: ذخیره‌ی api_id و api_hash */
    public function credentials(Request $request): JsonResponse
    {
        $data = $request->validate([
            'api_id' => ['required', 'regex:/^\d{3,12}$/'],
            'api_hash' => ['required', 'regex:/^[a-fA-F0-9]{32}$/'],
        ], [
            'api_id.regex' => 'api_id فقط عدد است (مثلاً 1234567).',
            'api_hash.regex' => 'api_hash یک رشته‌ی ۳۲ کاراکتری از حروف a-f و اعداد است.',
        ]);

        $user = $request->user();
        $account = $user->telegramAccount;
        if ($account && $account->isAuthorized() && $account->api_id === $data['api_id'] && $account->api_hash === strtolower($data['api_hash'])) {
            return $this->ok('تغییری نبود.', ['step' => 5]);
        }
        if ($account && $account->status !== TelegramAccount::NEW) {
            // کلید جدید = نشست قبلی دیگر معتبر نیست
            $this->runner->call($account, 'logout');
            $account->refresh();
        }
        $account = TelegramAccount::updateOrCreate(['user_id' => $user->id], [
            'api_id' => $data['api_id'],
            'api_hash' => strtolower($data['api_hash']),
            'status' => TelegramAccount::NEW,
            'is_active' => false,
        ]);

        return $this->ok('کلیدها ذخیره شد. حالا شماره‌ی تلگرام را وارد کنید.', ['step' => 2]);
    }

    /** مرحله‌ی ۲: ارسال کد ورود */
    public function sendCode(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $phone = self::intl($request->input('phone'));
        if (! preg_match('/^\+\d{8,15}$/', $phone)) {
            return $this->fail('شماره را با کد کشور وارد کنید، مثل +989121234567');
        }
        if ($r = $this->limit($request, 'tg-code', 5, 600)) {
            return $r;
        }
        $res = $this->runner->call($account, 'send-code', ['phone' => $phone]);

        return $this->result($res, ['step' => 3, 'phone' => $phone]);
    }

    /** مرحله‌ی ۳: کد ورود */
    public function verifyCode(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $code = preg_replace('/\D/', '', AuthController::phone($request->input('code')));
        if (strlen($code) < 4) {
            return $this->fail('کد ورود را کامل وارد کنید.');
        }
        if ($r = $this->limit($request, 'tg-verify', 10, 600)) {
            return $r;
        }
        $res = $this->runner->call($account, 'verify-code', ['code' => $code]);
        $account->refresh();

        return $this->result($res, ['step' => $account->step(), 'hint' => $account->password_hint, 'me' => $this->me($account)]);
    }

    /** مرحله‌ی ۴: رمز دومرحله‌ای */
    public function verifyPassword(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $request->validate(['password' => 'required|string|max:200']);
        if ($r = $this->limit($request, 'tg-verify', 10, 600)) {
            return $r;
        }
        $res = $this->runner->call($account, 'verify-password', ['password' => $request->input('password')]);
        $account->refresh();

        return $this->result($res, ['step' => $account->step(), 'me' => $this->me($account)]);
    }

    /** دکمه‌ی روشن/خاموش */
    public function toggle(Request $request): JsonResponse
    {
        $account = $this->account($request);
        if (! $account->isAuthorized()) {
            return $this->fail('اول اتصال به تلگرام را کامل کنید.');
        }
        $on = $request->boolean('on');
        if ($on && ! $account->is_active) {
            // از همین لحظه: فقط پیام‌هایی که از حالا می‌رسند ذخیره می‌شوند
            $account->cursors()->delete();
            $account->update(['is_active' => true, 'connected_at' => now(), 'last_synced_at' => null, 'last_error' => null]);
        } elseif (! $on) {
            $account->update(['is_active' => false]);
        }

        return $this->ok($on ? 'سیستم روشن شد؛ پیام‌های جدید دریافت می‌شوند.' : 'سیستم خاموش شد.', $this->state($account->fresh()));
    }

    /** یک دور کوتاه همگام‌سازی (از مرورگر وقتی پنل باز است، یا دکمه‌ی «دریافت الان») */
    public function sync(Request $request): JsonResponse
    {
        $account = $this->account($request);
        if (! $account->is_active || ! $account->isAuthorized()) {
            return $this->ok('سیستم خاموش است.', $this->state($account));
        }
        if ($r = $this->limit($request, 'tg-sync', 12, 60)) {
            return $r;
        }
        $res = $this->runner->call($account, 'sync', ['budget' => config('telegram.sync_budget', 20)]);
        $account->refresh();

        return response()->json($res + $this->state($account) + ['ok' => false], ($res['ok'] ?? false) || ($res['busy'] ?? false) ? 200 : 422);
    }

    public function status(Request $request): JsonResponse
    {
        $account = $request->user()->telegramAccount;

        return response()->json($account ? $this->state($account) : ['connected' => false]);
    }

    /**
     * چراغ اتصال سایدبار: نتیجه‌ی آخرین بررسی از کش؛ هر TELEGRAM_PING_CACHE ثانیه یک بار
     * واقعاً (در یک اجرای کوتاه) به سرور تلگرام وصل می‌شود. force=1 بررسی دوباره (حداقل هر ۱۰ ثانیه).
     */
    public function ping(Request $request): JsonResponse
    {
        $account = $request->user()->telegramAccount;
        if (! $account) {
            return response()->json(self::pingView(null, null));
        }
        $key = TelegramService::pingKey($account->id);
        $cached = Cache::get($key);
        $age = $cached ? time() - (int) $cached['at'] : PHP_INT_MAX;
        $ttl = $request->boolean('force') ? 10 : (int) config('telegram.ping_cache', 60);
        if ($age < $ttl) {
            return response()->json(self::pingView($cached, $account));
        }
        $lock = Cache::lock('tg-ping-'.$account->id, (int) config('telegram.ping_timeout', 25) + 10);
        if (! $lock->get()) {
            return response()->json(self::pingView($cached, $account) + ['checking' => true]);
        }
        try {
            $res = $this->runner->call($account, 'ping', [], (float) config('telegram.ping_timeout', 25));
        } finally {
            $lock->release();
        }
        if (! empty($res['busy'])) {
            // همگام‌سازی در حال اجراست؛ یعنی همین حالا به تلگرام وصل است یا نتیجه‌اش به‌زودی ثبت می‌شود
            return response()->json(self::pingView($cached, $account) + ['checking' => true]);
        }
        $state = ($res['ok'] ?? false)
            ? ['state' => 'ok', 'authorized' => (bool) ($res['authorized'] ?? false), 'ms' => $res['ms'] ?? null]
            : ['state' => 'down', 'message' => $res['message'] ?? 'خطا'];
        $state['at'] = time();
        Cache::put($key, $state, 86400);

        return response()->json(self::pingView($state, $account));
    }

    /** متن و وضعیت چراغ (برای JSON و رندر اولیه‌ی سایدبار) */
    public static function pingView(?array $s, ?TelegramAccount $account): array
    {
        $proxy = count(TelegramProxy::all());
        $via = $proxy ? ' (از راه پروکسی)' : '';
        if (! $account) {
            return ['state' => 'none', 'label' => 'تلگرام وصل نشده', 'title' => 'اول کلیدهای اتصال را در صفحه‌ی «اتصال تلگرام» ثبت کنید.'];
        }
        if (! $s) {
            return ['state' => 'checking', 'label' => 'بررسی اتصال…', 'title' => 'در حال بررسی اتصال به تلگرام'.$via];
        }
        $ago = 'آخرین بررسی: '.Carbon::createFromTimestamp($s['at'], config('app.timezone'))->diffForHumans();
        if ($s['state'] === 'ok') {
            $ms = ! empty($s['ms']) ? ' · '.$s['ms'].'ms' : '';

            return [
                'state' => 'ok',
                'label' => 'تلگرام متصل'.$ms,
                'title' => ($s['authorized'] ? 'اتصال به سرور تلگرام برقرار است' : 'سرور تلگرام در دسترس است؛ هنوز وارد حساب نشده‌اید').$via.' — '.$ago,
            ];
        }

        return ['state' => 'down', 'label' => 'تلگرام قطع', 'title' => ($s['message'] ?? 'اتصال برقرار نشد').$via.' — '.$ago];
    }

    /** خروج از حساب تلگرام (کلیدها می‌مانند) */
    public function logout(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $res = $this->runner->call($account, 'logout');

        return $this->result($res, ['step' => 2]);
    }

    /** حذف کامل اتصال (کلیدها و نشست) */
    public function destroy(Request $request): JsonResponse
    {
        $account = $this->account($request);
        $this->runner->call($account, 'logout');
        $account->delete();

        return $this->ok('اتصال تلگرام حذف شد.', ['step' => 1]);
    }

    // ------------------------------------------------------------------

    private function account(Request $request): TelegramAccount
    {
        $account = $request->user()->telegramAccount;
        abort_unless($account, 422, 'اول api_id و api_hash را ثبت کنید.');

        return $account;
    }

    private function state(TelegramAccount $a): array
    {
        return [
            'connected' => $a->isAuthorized(),
            'active' => $a->is_active,
            'status' => $a->status,
            'last_sync' => $a->last_synced_at?->diffForHumans(),
            'last_sync_ts' => $a->last_synced_at?->getTimestamp(),
            'last_error' => $a->last_error,
            'total' => $a->user->messages()->count(),
            'filtered' => $a->user->filteredMessages()->count(),
        ];
    }

    private function me(TelegramAccount $a): ?array
    {
        return $a->isAuthorized() ? ['name' => $a->tg_name, 'username' => $a->tg_username, 'phone' => $a->phone] : null;
    }

    private function limit(Request $request, string $name, int $max, int $decay): ?JsonResponse
    {
        $key = $name.':'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, $max)) {
            return $this->fail('درخواست زیاد بود؛ '.RateLimiter::availableIn($key).' ثانیه صبر کنید.', 429);
        }
        RateLimiter::hit($key, $decay);

        return null;
    }

    private function result(array $res, array $extra = []): JsonResponse
    {
        return ($res['ok'] ?? false) ? $this->ok($res['message'], $res + $extra) : $this->fail($res['message'] ?? 'خطا');
    }

    private function ok(string $message, array $data = []): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => $message] + $data);
    }

    private function fail(string $message, int $code = 422): JsonResponse
    {
        return response()->json(['ok' => false, 'message' => $message], $code);
    }

    /** ۰۹۱۲… → +98912… */
    public static function intl(?string $v): string
    {
        $v = AuthController::phone($v);
        if (preg_match('/^09\d{9}$/', $v)) {
            return '+98'.substr($v, 1);
        }
        if ($v !== '' && $v[0] !== '+' && str_starts_with($v, '00')) {
            return '+'.substr($v, 2);
        }
        if ($v !== '' && $v[0] !== '+') {
            return '+'.$v;
        }

        return $v;
    }
}
