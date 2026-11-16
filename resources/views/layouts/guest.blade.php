<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — تله‌پنل</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body class="guest">
<div class="auth">
    <div class="auth-art">
        <div class="planes"><span></span><span></span><span></span></div>
        @include('partials.logo')
        <h2>تله‌پنل</h2>
        <p>پیام‌های تلگرام‌تان را در یک جا جمع کنید، با کلیدواژه فیلتر کنید و فقط مهم‌ها را نگه دارید.</p>
    </div>
    <div class="auth-card">
        @yield('content')
    </div>
</div>
<script src="{{ asset('js/app.js') }}?v=1"></script>
</body>
</html>
