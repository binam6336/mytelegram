<?php

namespace App\Providers;

use App\Services\Telegram\FakeGateway;
use App\Services\Telegram\MadelineGateway;
use App\Services\Telegram\TelegramGateway;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelegramGateway::class, config('telegram.fake') ? FakeGateway::class : MadelineGateway::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');
    }
}
