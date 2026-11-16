<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Message extends Model
{
    protected $fillable = [
        'user_id', 'telegram_account_id', 'chat_id', 'chat_title', 'chat_type',
        'message_id', 'sender_id', 'sender_name', 'is_outgoing', 'text', 'media_type', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['is_outgoing' => 'boolean', 'sent_at' => 'datetime'];
    }

    public function filtered(): HasOne
    {
        return $this->hasOne(FilteredMessage::class);
    }
}
