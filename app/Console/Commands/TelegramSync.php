<?php

namespace App\Console\Commands;

use App\Models\TelegramAccount;
use App\Services\Telegram\TelegramService;
use Illuminate\Console\Command;

/**
 * همگام‌سازی کوتاه همه‌ی حساب‌های روشن. با کران هاست هر دقیقه اجرا و تمام می‌شود:
 *   * * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
 */
class TelegramSync extends Command
{
    protected $signature = 'telegram:sync {--account= : فقط یک حساب} {--budget=45 : کل زمان مجاز (ثانیه)}';

    protected $description = 'دریافت پیام‌های جدید حساب‌های روشن تلگرام (اجرای کوتاه)';

    public function handle(TelegramService $service): int
    {
        $started = microtime(true);
        $budget = (float) $this->option('budget');

        $accounts = TelegramAccount::where('status', TelegramAccount::AUTHORIZED)->where('is_active', true)
            ->when($this->option('account'), fn ($q, $id) => $q->whereKey($id))
            ->orderByRaw('last_synced_at IS NOT NULL, last_synced_at') // قدیمی‌ترها اول
            ->get();

        foreach ($accounts as $account) {
            $left = $budget - (microtime(true) - $started);
            if ($left < 5) {
                break;
            }
            $res = $service->run($account, 'sync', ['budget' => min($left - 2, (float) config('telegram.sync_budget', 20))]);
            $this->line('#'.$account->id.': '.$res['message']);
        }

        return self::SUCCESS;
    }
}
