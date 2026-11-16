{{-- دکمه‌ی بزرگ روشن/خاموش سیستم دریافت پیام --}}
<div class="power-wrap" data-power data-url="{{ route('telegram.toggle') }}" data-on="{{ $account->is_active ? 1 : 0 }}">
    <button type="button" class="power {{ $account->is_active ? 'is-on' : 'is-off' }}" data-power-btn
            aria-pressed="{{ $account->is_active ? 'true' : 'false' }}" aria-label="روشن یا خاموش کردن دریافت پیام">
        <span class="ring"></span>
        <svg viewBox="0 0 24 24"><path d="M12 3v9M6.3 6.3a8 8 0 1 0 11.4 0"/></svg>
    </button>
    <div class="power-t">
        <b data-power-label>{{ $account->is_active ? 'روشن — در حال دریافت پیام‌ها' : 'خاموش' }}</b>
        <small data-power-sub>{{ $account->is_active
            ? 'آخرین دریافت: '.($account->last_synced_at?->diffForHumans() ?? 'هنوز نه')
            : 'با زدن دکمه، از همین لحظه پیام‌های جدید ذخیره می‌شوند.' }}</small>
    </div>
</div>
