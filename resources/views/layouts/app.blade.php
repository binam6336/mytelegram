<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل') — تله‌پنل</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
@php($acc = auth()->user()->telegramAccount)
<body data-sync-url="{{ route('telegram.sync') }}"
      data-sync-interval="{{ config('telegram.browser_interval') }}"
      data-sync-active="{{ $acc && $acc->is_active && $acc->isAuthorized() ? 1 : 0 }}">
<div class="shell">
    <aside class="side" id="side">
        <a href="{{ route('dashboard') }}" class="brand">
            @include('partials.logo')
            <span>تله‌پنل<small>مدیریت پیام تلگرام</small></span>
        </a>
        <nav class="nav">
            <a href="{{ route('dashboard') }}" @class(['on' => request()->routeIs('dashboard')])>
                <svg viewBox="0 0 24 24"><path d="M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10"/></svg>داشبورد</a>
            <a href="{{ route('telegram.connect') }}" @class(['on' => request()->routeIs('telegram.*')])>
                <svg viewBox="0 0 24 24"><path d="M21 4L3 11l6 2 2 6 3-4 5 4 2-15zM9 13l9-6"/></svg>اتصال تلگرام
                <i @class(['dot', 'is-on' => $acc && $acc->is_active, 'is-off' => ! $acc || ! $acc->is_active])></i></a>
            <a href="{{ route('messages') }}" @class(['on' => request()->routeIs('messages*')])>
                <svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z"/></svg>همه‌ی پیام‌ها</a>
            <a href="{{ route('filters') }}" @class(['on' => request()->routeIs('filters*')])>
                <svg viewBox="0 0 24 24"><path d="M3 5h18l-7 8v6l-4-2v-4z"/></svg>فیلتر پیام</a>
        </nav>
        <div class="me">
            <div class="avatar">{{ mb_substr(auth()->user()->phone, -2) }}</div>
            <div class="me-t"><b dir="ltr">{{ auth()->user()->phone }}</b><small>{{ $acc?->tg_name ?: 'تلگرام وصل نیست' }}</small></div>
            <form method="post" action="{{ route('logout') }}">@csrf
                <button class="icon-btn" title="خروج" aria-label="خروج"><svg viewBox="0 0 24 24"><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/></svg></button>
            </form>
        </div>
    </aside>
    <main class="main">
        <header class="top">
            <button class="icon-btn burger" data-burger aria-label="منو"><svg viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            <h1>@yield('title')</h1>
            <span class="live" data-live-pill hidden><i></i><span>در حال دریافت</span></span>
        </header>
        @if (session('ok'))<div class="flash ok">{{ session('ok') }}</div>@endif
        @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
        @yield('content')
    </main>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<script src="{{ asset('js/app.js') }}?v=1"></script>
</body>
</html>
