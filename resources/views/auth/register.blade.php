@extends('layouts.guest')
@section('title', 'ثبت‌نام')
@section('content')
    <h1>ساخت حساب</h1>
    <p class="muted">فقط شماره‌ی موبایل و یک رمز عبور.</p>
    @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('register') }}" class="form">
        @csrf
        <label>شماره‌ی موبایل
            <input name="phone" value="{{ old('phone') }}" inputmode="tel" dir="ltr" placeholder="09121234567" required autofocus>
        </label>
        <label>رمز عبور
            <input type="password" name="password" dir="ltr" minlength="8" required autocomplete="new-password">
        </label>
        <label>تکرار رمز عبور
            <input type="password" name="password_confirmation" dir="ltr" minlength="8" required autocomplete="new-password">
        </label>
        <button class="btn primary block">ثبت‌نام و ورود</button>
    </form>
    <p class="switch">حساب دارید؟ <a href="{{ route('login') }}">وارد شوید</a></p>
@endsection
