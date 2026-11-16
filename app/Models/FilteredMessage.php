<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FilteredMessage extends Model
{
    protected $fillable = [
        'user_id', 'message_id', 'telegram_account_id', 'chat_id', 'chat_title', 'chat_type',
        'tg_message_id', 'sender_name', 'is_outgoing', 'text', 'matched_keywords', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'is_outgoing' => 'boolean',
            'matched_keywords' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
