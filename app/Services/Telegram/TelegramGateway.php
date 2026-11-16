<?php

namespace App\Services\Telegram;

use App\Models\TelegramAccount;

/**
 * لایه‌ی ارتباط با کلاینت تلگرام (MTProto). هر متد باید کوتاه باشد:
 * اتصال باز می‌شود، کار انجام می‌شود و پردازش تمام می‌شود (بدون اتصال ماندگار).
 */
interface TelegramGateway
{
    /** ارسال کد ورود به شماره‌ی تلگرام */
    public function sendCode(TelegramAccount $account, string $phone): void;

    /**
     * تأیید کد. خروجی: ['status' => 'authorized', 'me' => [...]]
     * یا ['status' => 'password_needed', 'hint' => '...']
     */
    public function verifyCode(TelegramAccount $account, string $code): array;

    /** تأیید رمز دومرحله‌ای؛ خروجی: ['me' => [...]] */
    public function verifyPassword(TelegramAccount $account, string $password): array;

    /**
     * دریافت پیام‌های جدید از زمان آخرین همگام‌سازی.
     *
     * @param  array<string,int>  $cursors  peer_id => آخرین شناسه‌ی پیام ذخیره‌شده
     * @param  int  $since  مهر زمانی؛ پیام‌های قدیمی‌تر از این لحظه نادیده گرفته می‌شوند
     * @param  int|null  $lastSync  مهر زمانی آخرین همگام‌سازی (برای توقف زودهنگام مرور گفتگوها)
     * @return array{messages: list<array>, cursors: array<string,int>, complete: bool}
     */
    public function fetchNew(TelegramAccount $account, array $cursors, int $since, ?int $lastSync, float $budget): array;

    /** بررسی اتصال به سرور تلگرام؛ خروجی: ['authorized' => bool, 'ms' => int] */
    public function ping(TelegramAccount $account): array;

    /** خروج از حساب تلگرام و حذف نشست */
    public function logout(TelegramAccount $account): void;
}
