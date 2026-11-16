<?php

namespace App\Services;

use App\Models\FilteredMessage;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * تطبیق پیام‌ها با کلیدواژه‌های کاربر و ذخیره‌ی نتیجه در جدول جدای filtered_messages.
 */
class KeywordFilter
{
    /** یکسان‌سازی متن فارسی/عربی برای جست‌وجو */
    public static function normalize(?string $text): string
    {
        $text = (string) $text;
        $text = strtr($text, [
            'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا',
            "\u{200C}" => ' ', "\u{200F}" => '', "\u{200E}" => '',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text); // اعراب و کشیده
        $text = preg_replace('/\s+/u', ' ', $text);

        return mb_strtolower(trim($text));
    }

    /** @param list<string> $words  @return list<string> کلیدواژه‌های پیداشده */
    public function match(?string $text, array $words): array
    {
        $hay = self::normalize($text);
        if ($hay === '') {
            return [];
        }
        $found = [];
        foreach ($words as $w) {
            $needle = self::normalize($w);
            if ($needle !== '' && mb_strpos($hay, $needle) !== false) {
                $found[] = $w;
            }
        }

        return $found;
    }

    /** @return list<string> */
    public function words(int $userId): array
    {
        return User::find($userId)?->keywords()->pluck('word')->all() ?? [];
    }

    /**
     * پیام‌ها را بررسی و موارد منطبق را در filtered_messages ثبت می‌کند.
     *
     * @param  Collection<int, Message>  $messages
     * @return int تعداد پیام‌های فیلترشده‌ی جدید
     */
    public function apply(int $userId, Collection $messages, ?array $words = null): int
    {
        $words ??= $this->words($userId);
        if (! $words || $messages->isEmpty()) {
            return 0;
        }
        $rows = [];
        $now = now();
        foreach ($messages as $m) {
            $hits = $this->match($m->text, $words);
            if (! $hits) {
                continue;
            }
            $rows[] = [
                'user_id' => $userId,
                'message_id' => $m->id,
                'telegram_account_id' => $m->telegram_account_id,
                'chat_id' => $m->chat_id,
                'chat_title' => $m->chat_title,
                'chat_type' => $m->chat_type,
                'tg_message_id' => $m->message_id,
                'sender_name' => $m->sender_name,
                'is_outgoing' => $m->is_outgoing,
                'text' => $m->text,
                'matched_keywords' => json_encode(array_values($hits), JSON_UNESCAPED_UNICODE),
                'sent_at' => $m->sent_at,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (! $rows) {
            return 0;
        }
        // اگر قبلاً فیلتر شده بود فقط لیست کلیدواژه‌ها به‌روز می‌شود
        FilteredMessage::upsert($rows, ['telegram_account_id', 'chat_id', 'tg_message_id'], ['matched_keywords', 'message_id', 'updated_at']);

        return count($rows);
    }

    /** بررسی دوباره‌ی همه‌ی پیام‌های ذخیره‌شده با کلیدواژه‌های فعلی */
    public function rescan(int $userId): int
    {
        $words = $this->words($userId);
        if (! $words) {
            return 0;
        }
        $total = 0;
        Message::where('user_id', $userId)->whereNotNull('text')
            ->chunkById(500, function ($chunk) use ($userId, $words, &$total) {
                $total += $this->apply($userId, $chunk, $words);
            });

        return $total;
    }
}
