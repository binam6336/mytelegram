<?php

namespace App\Services\Telegram;

use App\Models\TelegramAccount;
use RuntimeException;

/**
 * درگاه آزمایشی برای توسعه/تست بدون تلگرام واقعی (TELEGRAM_FAKE=true).
 * کد ورود همیشه 12345 است؛ رمز دومرحله‌ای اگر کد 22222 باشد خواسته می‌شود و رمزش «secret» است.
 * هر بار همگام‌سازی چند پیام تصادفی می‌سازد.
 */
class FakeGateway implements TelegramGateway
{
    private const CHATS = [
        ['id' => '1001', 'title' => 'علی رضایی', 'type' => 'user'],
        ['id' => '-2002', 'title' => 'گروه خانواده', 'type' => 'group'],
        ['id' => '-1003003', 'title' => 'تیم فروش', 'type' => 'supergroup'],
        ['id' => '-1004004', 'title' => 'کانال اخبار فناوری', 'type' => 'channel'],
    ];

    private const TEXTS = [
        'سلام، قرارداد جدید آماده‌ی امضاست.', 'جلسه‌ی فردا ساعت ۱۰ برگزار می‌شود.', 'لطفاً فاکتور پرداخت را بفرستید.',
        'این موضوع فوری است، تماس بگیرید.', 'عکس‌های سفر را دیدی؟', 'گزارش فروش ماه گذشته ضمیمه شد.',
        'نسخه‌ی جدید اپلیکیشن منتشر شد.', 'شام امشب خانه‌ی مادربزرگ هستیم.', 'مبلغ پرداخت شد، رسیدش را ببینید.',
        'Meeting moved to Monday.', 'یادت نره نان بخری!', 'قیمت دلار امروز تغییر کرد.',
    ];

    public function sendCode(TelegramAccount $account, string $phone): void
    {
        usleep(400_000);
    }

    public function verifyCode(TelegramAccount $account, string $code): array
    {
        usleep(300_000);
        if ($code === '22222') {
            return ['status' => 'password_needed', 'hint' => 'اسم گربه'];
        }
        if ($code !== '12345') {
            throw new RuntimeException('PHONE_CODE_INVALID');
        }

        return ['status' => 'authorized', 'me' => ['id' => '777', 'name' => 'کاربر آزمایشی', 'username' => 'demo_user']];
    }

    public function verifyPassword(TelegramAccount $account, string $password): array
    {
        if ($password !== 'secret') {
            throw new RuntimeException('PASSWORD_HASH_INVALID');
        }

        return ['me' => ['id' => '777', 'name' => 'کاربر آزمایشی', 'username' => 'demo_user']];
    }

    public function fetchNew(TelegramAccount $account, array $cursors, int $since, ?int $lastSync, float $budget): array
    {
        $messages = [];
        foreach (self::CHATS as $chat) {
            $last = $cursors[$chat['id']] ?? null;
            if ($last === null) {
                $cursors[$chat['id']] = 100; // نقطه‌ی شروع: پیام‌های قبلی نادیده
                continue;
            }
            $n = random_int(0, 3);
            for ($i = 1; $i <= $n; $i++) {
                $out = random_int(0, 5) === 0;
                $messages[] = [
                    'chat_id' => $chat['id'],
                    'chat_title' => $chat['title'],
                    'chat_type' => $chat['type'],
                    'message_id' => $last + $i,
                    'sender_id' => $out ? '777' : '1001',
                    'sender_name' => $out ? 'من' : ($chat['type'] === 'user' ? $chat['title'] : 'مریم احمدی'),
                    'is_outgoing' => $out,
                    'text' => self::TEXTS[array_rand(self::TEXTS)],
                    'media_type' => random_int(0, 6) === 0 ? 'Photo' : null,
                    'sent_at' => time() - random_int(0, 20),
                ];
            }
            $cursors[$chat['id']] = $last + $n;
        }

        return ['messages' => $messages, 'cursors' => $cursors, 'complete' => true];
    }

    public function logout(TelegramAccount $account): void {}
}
