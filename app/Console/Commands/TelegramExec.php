<?php

namespace App\Console\Commands;

use App\Models\TelegramAccount;
use App\Services\Telegram\TelegramRunner;
use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;

/** اجرای یک عملیات کوتاه تلگرام؛ ورودی JSON از stdin، خروجی JSON بعد از نشانه. */
class TelegramExec extends Command
{
    protected $signature = 'telegram:exec {account} {action}';

    protected $description = 'اجرای یک عملیات کوتاه روی حساب تلگرام (برای استفاده‌ی داخلی پنل)';

    public function handle(TelegramService $service): int
    {
        $payload = [];
        if (! stream_isatty(STDIN)) {
            $payload = json_decode((string) stream_get_contents(STDIN), true) ?: [];
        }
        $account = TelegramAccount::find((int) $this->argument('account'));
        $result = $account
            ? $service->run($account, (string) $this->argument('action'), $payload)
            : ['ok' => false, 'message' => 'حساب پیدا نشد.'];

        $this->output->writeln(TelegramRunner::MARK.json_encode($result, JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
