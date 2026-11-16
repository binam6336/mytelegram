@extends('layouts.guest')
@section('title', 'ورود')
@section('content')
    <h1>ورود به پنل</h1>
    <p class="muted">با شماره‌ای که ثبت‌نام کرده‌اید وارد شوید.</p>
    @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('login') }}" class="form">
        @csrf
        <label>شماره‌ی موبایل
            <input name="phone" value="{{ old('phone') }}" inputmode="tel" dir="ltr" placeholder="09121234567" required autofocus>
        </label>
        <label>رمز عبور
            <input type="password" name="password" dir="ltr" required autocomplete="current-password">
        </label>
        <label class="check"><input type="checkbox" name="remember" value="1" checked> مرا به خاطر بسپار</label>
        <button class="btn primary block">ورود</button>
    </form>
    <p class="switch">حساب ندارید؟ <a href="{{ route('register') }}">ثبت‌نام کنید</a></p>
@endsection
