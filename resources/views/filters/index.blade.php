@extends('layouts.app')
@section('title', 'فیلتر پیام')
@section('content')
@php
    $words = $keywords->pluck('word')->all();
    $highlight = function (?string $text) use ($words) {
        $html = nl2br(e((string) $text));
        foreach ($words as $w) {
            $html = preg_replace('/('.preg_quote(e($w), '/').')(?![^<]*>)/iu', '<mark>$1</mark>', $html);
        }
        return $html;
    };
@endphp
<div class="cols">
    <section class="card kw-card">
        <div class="card-h"><h3>کلیدواژه‌ها</h3><span class="muted small">{{ $keywords->count() }} از ۲۰۰</span></div>
        <form method="post" action="{{ route('keywords.store') }}" class="kw-add">
            @csrf
            <input name="words" placeholder="مثلاً: فوری، قرارداد، پرداخت" required>
            <button class="btn primary sm">افزودن</button>
        </form>
        <p class="muted small">چند کلیدواژه را با ویرگول جدا کنید. «ي/ی» و «ك/ک» و ارقام فارسی/انگلیسی یکی حساب می‌شوند.</p>
        <div class="chips">
            @forelse ($keywords as $k)
                <span @class(['chip', 'on' => $kw === $k->word])>
                    <a href="{{ route('filters', ['kw' => $kw === $k->word ? null : $k->word]) }}">{{ $k->word }} <b>{{ $counts[$k->word] ?? 0 }}</b></a>
                    <form method="post" action="{{ route('keywords.delete', $k->id) }}">@csrf<button aria-label="حذف {{ $k->word }}">×</button></form>
                </span>
            @empty
                <span class="muted">هنوز کلیدواژه‌ای تعریف نشده.</span>
            @endforelse
        </div>
        @if ($keywords->isNotEmpty())
            <form method="post" action="{{ route('filters.rescan') }}">@csrf
                <button class="btn ghost sm block">بررسی دوباره‌ی همه‌ی پیام‌ها</button>
            </form>
        @endif
        <div class="note small">پیام‌های تازه هنگام دریافت خودکار بررسی می‌شوند. پیام‌های منطبق در جدول جداگانه ذخیره می‌شوند و با «حذف همه‌ی پیام‌ها» پاک نمی‌شوند.</div>
    </section>

    <section class="card">
        <form class="toolbar" method="get">
            @if ($kw)<input type="hidden" name="kw" value="{{ $kw }}">@endif
            <input type="search" name="q" value="{{ $q }}" placeholder="جست‌وجو در فیلترشده‌ها…">
            <button class="btn primary sm">جست‌وجو</button>
            @if ($kw || $q)<a class="btn ghost sm" href="{{ route('filters') }}">پاک کردن فیلتر</a>@endif
        </form>
        <p class="muted small">{{ number_format($items->total()) }} پیام فیلترشده @if ($kw) با «{{ $kw }}» @endif</p>
        @forelse ($items as $m)
            <div class="fl-row">
                @include('partials.message', ['m' => $m, 'highlight' => $highlight])
                <div class="fl-meta">
                    @foreach ((array) $m->matched_keywords as $w)<span class="tag green">{{ $w }}</span>@endforeach
                    <form method="post" action="{{ route('filters.delete', $m->id) }}">@csrf
                        <button class="link-btn" data-confirm="از فیلترشده‌ها حذف شود؟">حذف</button>
                    </form>
                </div>
            </div>
        @empty
            <div class="empty">{{ $keywords->isEmpty() ? 'اول یک کلیدواژه اضافه کنید.' : 'هنوز پیامی با این کلیدواژه‌ها پیدا نشده.' }}</div>
        @endforelse
        {{ $items->links() }}
    </section>
</div>
@endsection
