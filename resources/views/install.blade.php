@extends('layouts.guest')
@section('title', 'نصب')
@section('content')
    <h1>وضعیت نصب</h1>
    @if (session('ok'))<div class="flash ok">{{ session('ok') }}</div>@endif
    @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif

    <ul class="checks">
        <li class="good"><b>PHP وب‌سایت:</b> <span dir="ltr">{{ $web_version }}</span> <small>({{ $web_sapi }})</small></li>

        @if ($cli)
            <li class="good"><b>PHP خط فرمان (برای دریافت پیام):</b> <span dir="ltr">{{ $cli_version }}</span>
                <code dir="ltr">{{ $cli }}</code></li>
        @else
            <li class="warn"><b>PHP خط فرمان هم‌نسخه پیدا نشد.</b>
                عملیات تلگرام داخل خود درخواست وب اجرا می‌شود (کار می‌کند). برای کران‌جاب، مسیر php نسخه‌ی {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }} را از پشتیبانی هاست بپرسید
                و در <code>.env</code> بگذارید: <code dir="ltr">TELEGRAM_PHP_BINARY=…</code></li>
        @endif

        @if ($plain_version && $plain_version !== $cli_version)
            <li class="warn"><b>دستور ساده‌ی <code>php</code> روی این سرور نسخه‌ی <span dir="ltr">{{ $plain_version }}</span> است</b>،
                نه نسخه‌ی وب. برای همین «php artisan …» یا composer در ترمینال/کران خطای نسخه می‌دهد.
                به‌جای <code>php</code> مسیر کامل بالا را بنویسید، یا جدول‌ها را با دکمه‌ی همین صفحه بسازید.</li>
        @endif

        @if ($proxies)
            @foreach ($proxies as $px)
                @if ($px['ok'])
                    <li class="good"><b>پروکسی <span dir="ltr">{{ $px['label'] }}</span>:</b> هاست به آن می‌رسد
                        (<span dir="ltr">{{ $px['ip'] }}</span>، اتصال TCP در {{ $px['ms'] }}ms). وضعیت نهایی اتصال به تلگرام را چراغ سایدبار پنل نشان می‌دهد.</li>
                @else
                    <li class="bad"><b>پروکسی <span dir="ltr">{{ $px['label'] }}</span>:</b> {{ $px['error'] }}</li>
                @endif
            @endforeach
        @elseif ($proxy_raw)
            <li class="bad"><b>TELEGRAM_PROXY قابل خواندن نیست.</b> قالب درست: <code dir="ltr">tg://proxy?server=…&amp;port=…&amp;secret=…</code> یا <code dir="ltr">socks5://host:port</code></li>
        @else
            <li class="warn"><b>پروکسی تلگرام تنظیم نشده.</b> اگر هاست در ایران است، <code>TELEGRAM_PROXY</code> را در <code>.env</code> پر کنید.</li>
        @endif

        @if ($db_ok)
            <li class="good"><b>اتصال دیتابیس:</b> برقرار است.</li>
        @else
            <li class="bad"><b>اتصال دیتابیس برقرار نشد.</b> مقادیر DB_* در <code>.env</code> را بررسی کنید.
                <small dir="ltr" class="err-msg">{{ $db_error }}</small></li>
        @endif

        @if ($db_ok)
            @if ($pending)
                <li class="warn"><b>{{ $pending }} migration اجرا نشده</b> (جدول‌ها کامل ساخته نشده‌اند).</li>
            @else
                <li class="good"><b>جدول‌های دیتابیس:</b> کامل است.</li>
            @endif
        @endif
    </ul>

    @if ($db_ok && $pending)
        <form method="post" action="{{ route('install.migrate') }}" class="form">
            @csrf
            <button class="btn primary block">ساخت جدول‌های دیتابیس</button>
        </form>
    @elseif ($db_ok)
        <a class="btn primary block" href="{{ route('register') }}">رفتن به ثبت‌نام</a>
    @endif

    <h3>کران‌جاب (هر دقیقه)</h3>
    <p class="muted small">در cPanel ← Cron Jobs همین خط را با زمان‌بندی «Once Per Minute» اضافه کنید:</p>
    <pre class="cron" dir="ltr">{{ $cron }}</pre>
@endsection
