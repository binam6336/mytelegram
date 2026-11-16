@extends('layouts.app')
@section('title', 'داشبورد')
@section('content')
    @if (! $account || ! $account->isAuthorized())
        <section class="hero">
            <div class="hero-t">
                <h2>به تله‌پنل خوش آمدید 👋</h2>
                <p>برای شروع، کلاینت تلگرام خود را با <b dir="ltr">api_id</b> و <b dir="ltr">api_hash</b> وصل کنید. چهار قدم کوتاه است.</p>
                <a class="btn light" href="{{ route('telegram.connect') }}">شروع اتصال به تلگرام ←</a>
            </div>
            <div class="hero-art">@include('partials.logo')</div>
        </section>
    @else
        <section class="hero is-connected">
            <div class="hero-t">
                <h2>{{ $account->tg_name ?: 'حساب تلگرام' }} @if ($account->tg_username)<small dir="ltr">{{ '@'.$account->tg_username }}</small>@endif</h2>
                <p>هر زمان سیستم روشن باشد پیام‌های رسیده در دوره‌های کوتاه دریافت و ذخیره می‌شوند.</p>
                @if ($account->last_error)<p class="hero-err">⚠ {{ $account->last_error }}</p>@endif
                <button class="btn light sm" data-sync-now @disabled(! $account->is_active)>دریافت همین الان</button>
            </div>
            @include('partials.power', ['account' => $account])
        </section>
    @endif

    <section class="stats">
        <div class="stat"><span>کل پیام‌ها</span><b data-stat="total">{{ number_format($stats['messages']) }}</b></div>
        <div class="stat"><span>امروز</span><b>{{ number_format($stats['today']) }}</b></div>
        <div class="stat green"><span>فیلترشده‌ها</span><b data-stat="filtered">{{ number_format($stats['filtered']) }}</b></div>
        <div class="stat"><span>کلیدواژه‌ها</span><b>{{ number_format($stats['keywords']) }}</b></div>
        <div class="stat"><span>گفتگوها</span><b>{{ number_format($stats['chats']) }}</b></div>
    </section>

    <section class="card">
        <div class="card-h"><h3>آخرین پیام‌ها</h3><a href="{{ route('messages') }}">همه ←</a></div>
        @forelse ($latest as $m)
            @include('partials.message', ['m' => $m])
        @empty
            <div class="empty">هنوز پیامی ذخیره نشده است.</div>
        @endforelse
    </section>
@endsection
