@extends('layouts.app')
@section('title', 'اتصال به تلگرام')
@section('content')
@php($urls = ['credentials' => route('telegram.credentials'), 'code' => route('telegram.code'), 'verify' => route('telegram.verify'), 'password' => route('telegram.password'), 'logout' => route('telegram.logout'), 'delete' => route('telegram.delete')])
<div class="wizard" data-wizard data-step="{{ $step }}"
     data-urls="{{ json_encode($urls) }}">

    <ol class="steps" aria-label="مراحل اتصال">
        <li data-s="1"><i>۱</i><span>کلیدهای API</span></li>
        <li data-s="2"><i>۲</i><span>شماره‌ی تلگرام</span></li>
        <li data-s="3"><i>۳</i><span>کد ورود</span></li>
        <li data-s="4"><i>۴</i><span>رمز دومرحله‌ای</span></li>
        <li data-s="5"><i>✓</i><span>متصل</span></li>
    </ol>

    <div class="sky" aria-hidden="true"><div class="plane">@include('partials.logo')</div><div class="trail"></div></div>

    <div class="panes">
        {{-- مرحله ۱ --}}
        <section class="pane" data-pane="1">
            <h2>کلیدهای اتصال کلاینت</h2>
            <p class="muted">این دو مقدار را از سایت رسمی تلگرام بگیرید؛ فقط یک بار لازم است.</p>
            <div class="guide">
                <div><i>1</i>به <a href="https://my.telegram.org" target="_blank" rel="noopener" dir="ltr">my.telegram.org</a> بروید و با شماره‌ی تلگرامتان وارد شوید.</div>
                <div><i>2</i>گزینه‌ی <b dir="ltr">API development tools</b> را بزنید و یک اپ (هر نامی) بسازید.</div>
                <div><i>3</i><b dir="ltr">App api_id</b> و <b dir="ltr">App api_hash</b> را اینجا کپی کنید.</div>
            </div>
            <form data-form="credentials" class="form grid2">
                <label>client_id <small>(api_id)</small>
                    <input name="api_id" inputmode="numeric" dir="ltr" placeholder="1234567" value="{{ $account?->api_id }}" required>
                </label>
                <label>client_hash <small>(api_hash)</small>
                    <span class="pw"><input name="api_hash" dir="ltr" placeholder="0123456789abcdef0123456789abcdef" maxlength="32" {{ $account ? '' : 'required' }}
                        value="{{ $account?->api_hash }}" type="password" autocomplete="off"><button type="button" data-reveal aria-label="نمایش">👁</button></span>
                </label>
                <button class="btn primary">ذخیره و ادامه</button>
            </form>
        </section>

        {{-- مرحله ۲ --}}
        <section class="pane" data-pane="2">
            <h2>شماره‌ی حساب تلگرام</h2>
            <p class="muted">کد ورود از طرف خود تلگرام (داخل اپ تلگرام) برایتان فرستاده می‌شود.</p>
            <form data-form="code" class="form">
                <label>شماره با کد کشور
                    <input name="phone" dir="ltr" inputmode="tel" value="{{ $phoneHint }}" placeholder="+989121234567" required>
                </label>
                <div class="row">
                    <button class="btn primary">ارسال کد</button>
                    <button type="button" class="btn ghost" data-go="1">→ تغییر کلیدها</button>
                </div>
            </form>
        </section>

        {{-- مرحله ۳ --}}
        <section class="pane" data-pane="3">
            <h2>کد ورود را وارد کنید</h2>
            <p class="muted">کد ۵ رقمی در پیام «Telegram» داخل اپ آمده است. <span data-phone-label dir="ltr">{{ $account?->phone }}</span></p>
            <form data-form="verify" class="form">
                <div class="otp" data-otp>
                    @for ($i = 0; $i < 5; $i++)<input inputmode="numeric" maxlength="1" dir="ltr" aria-label="رقم {{ $i + 1 }}">@endfor
                </div>
                <input type="hidden" name="code">
                <div class="row">
                    <button class="btn primary">تأیید کد</button>
                    <button type="button" class="btn ghost" data-go="2">→ شماره‌ی دیگر / ارسال دوباره</button>
                </div>
            </form>
        </section>

        {{-- مرحله ۴ --}}
        <section class="pane" data-pane="4">
            <h2>رمز دومرحله‌ای</h2>
            <p class="muted">حساب شما تأیید دومرحله‌ای (Cloud Password) دارد.
                <span data-hint>@if ($account?->password_hint) راهنما: «{{ $account->password_hint }}» @endif</span></p>
            <form data-form="password" class="form">
                <label>رمز
                    <span class="pw"><input type="password" name="password" dir="ltr" required autocomplete="off"><button type="button" data-reveal aria-label="نمایش">👁</button></span>
                </label>
                <button class="btn primary">ورود</button>
            </form>
        </section>

        {{-- مرحله ۵ --}}
        <section class="pane" data-pane="5">
            <div class="done">
                <div class="burst">✓</div>
                <h2>به تلگرام وصل شدید</h2>
                <p class="muted" data-me>{{ $account?->tg_name }} @if ($account?->tg_username)<span dir="ltr">{{ '@'.$account->tg_username }}</span>@endif — <span dir="ltr">{{ $account?->phone }}</span></p>
            </div>
            @if ($account && $account->isAuthorized())
                @include('partials.power', ['account' => $account])
            @else
                <p class="center"><a class="btn primary" href="{{ route('telegram.connect') }}">نمایش کلید روشن/خاموش</a></p>
            @endif
            <div class="note">
                <b>بدون اتصال ماندگار:</b> سرور به تلگرام وصل نمی‌ماند؛ هر بار در یک اجرای کوتاه (کران‌جاب هر دقیقه،
                و وقتی پنل باز است هر {{ config('telegram.browser_interval') }} ثانیه) فقط پیام‌های جدید گرفته می‌شود و اتصال بسته می‌شود.
            </div>
            <div class="row center">
                <button type="button" class="btn ghost" data-action="logout" data-confirm="از حساب تلگرام خارج شوید؟ (پیام‌های ذخیره‌شده می‌مانند)">خروج از حساب تلگرام</button>
                <button type="button" class="btn danger-ghost" data-action="delete" data-confirm="اتصال و کلیدها کامل حذف شوند؟">حذف اتصال</button>
            </div>
        </section>
    </div>
    <div class="busy" data-busy hidden><span class="spin"></span><span data-busy-text>در حال ارتباط با تلگرام…</span></div>
</div>
@endsection
