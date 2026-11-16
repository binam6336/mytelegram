<?php

namespace App\Services\Telegram;

use App\Models\TelegramAccount;
use Illuminate\Support\Facades\Log;
use App\Support\PhpBinary;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * اجرای عملیات تلگرام از داخل درخواست وب.
 *
 * حالت process (پیش‌فرض): یک پردازش کوتاه `php artisan telegram:exec` اجرا می‌شود،
 * کارش را انجام می‌دهد و تمام می‌شود؛ درخواست وب منتظر نتیجه می‌ماند.
 * داده‌های حساس (کد، رمز) از stdin داده می‌شوند، نه آرگومان (در لیست پردازش‌ها دیده نمی‌شوند).
 */
class TelegramRunner
{
    public const MARK = '@@TG_RESULT@@';

    public function __construct(private TelegramService $service) {}

    public function call(TelegramAccount $account, string $action, array $payload = [], ?float $timeout = null): array
    {
        // بدون proc_open، یا وقتی php-cli هم‌نسخه‌ی وب پیدا نشد (مثلاً php خط فرمان 7.4 است)،
        // همان‌جا داخل درخواست وب اجرا می‌شود (باز هم کوتاه و بدون اتصال ماندگار).
        $php = config('telegram.mode') === 'inline' || ! function_exists('proc_open') ? null : PhpBinary::find();
        if ($php === null) {
            @set_time_limit((int) config('telegram.process_timeout', 60) + 15);

            return $this->service->run($account, $action, $payload);
        }

        $process = new Process(
            [$php, base_path('artisan'), 'telegram:exec', (string) $account->id, $action],
            base_path(),
            null,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $timeout ?? (float) config('telegram.process_timeout', 60),
        );
        @set_time_limit((int) config('telegram.process_timeout', 60) + 15);

        try {
            $process->run();
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException) {
            return ['ok' => false, 'timeout' => true, 'message' => 'پاسخی از تلگرام نرسید (زمان تمام شد). اگر هاست در ایران است پروکسی TELEGRAM_PROXY را بررسی کنید.'];
        } catch (Throwable $e) {
            Log::warning('telegram process failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'اجرای پردازش تلگرام ممکن نشد (شاید proc_open یا مسیر php روی هاست مشکل دارد؛ TELEGRAM_MODE=inline را امتحان کنید).'];
        }

        $out = $process->getOutput();
        $pos = strrpos($out, self::MARK);
        if ($pos !== false) {
            $json = json_decode(trim(substr($out, $pos + strlen(self::MARK))), true);
            if (is_array($json)) {
                return $json;
            }
        }
        Log::warning('telegram process bad output', [
            'code' => $process->getExitCode(),
            'out' => mb_substr($out, -1000),
            'err' => mb_substr($process->getErrorOutput(), -1000),
        ]);

        return ['ok' => false, 'message' => 'پاسخ نامعتبر از پردازش تلگرام؛ جزئیات در storage/logs.'];
    }
}
