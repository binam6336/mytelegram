<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['phone', 'name', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function telegramAccount(): HasOne
    {
        return $this->hasOne(TelegramAccount::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function filteredMessages(): HasMany
    {
        return $this->hasMany(FilteredMessage::class);
    }
}
