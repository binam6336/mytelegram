<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramChatCursor extends Model
{
    protected $fillable = ['telegram_account_id', 'peer_id', 'last_message_id'];
}
