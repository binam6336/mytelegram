<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramAccount extends Model
{
    public const NEW = 'new';
    public const CODE_SENT = 'code_sent';
    public const PASSWORD_NEEDED = 'password_needed';
    public const AUTHORIZED = 'authorized';

    protected $fillable = [
        'user_id', 'api_id', 'api_hash', 'phone', 'status', 'is_active',
        'tg_user_id', 'tg_name', 'tg_username', 'password_hint',
        'connected_at', 'last_synced_at', 'next_sync_at', 'last_error', 'synced_total',
    ];

    protected $hidden = ['api_hash'];

    protected function casts(): array
    {
        return [
            'api_hash' => 'encrypted',
            'is_active' => 'boolean',
            'connected_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'next_sync_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cursors(): HasMany
    {
        return $this->hasMany(TelegramChatCursor::class);
    }

    public function isAuthorized(): bool
    {
        return $this->status === self::AUTHORIZED;
    }

    /** مسیر فایل نشست MadelineProto این حساب */
    public function sessionPath(): string
    {
        return storage_path('app/telegram/session-'.$this->id.'.madeline');
    }

    /** شماره‌ی مرحله در صفحه‌ی اتصال (۱ تا ۴) */
    public function step(): int
    {
        return match ($this->status) {
            self::CODE_SENT => 3,
            self::PASSWORD_NEEDED => 4,
            self::AUTHORIZED => 5,
            default => 2,
        };
    }
}
