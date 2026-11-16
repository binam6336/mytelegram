@extends('layouts.app')
@section('title', 'همه‌ی پیام‌ها')
@section('content')
    <section class="card">
        <form class="toolbar" method="get">
            <input type="search" name="q" value="{{ $q }}" placeholder="جست‌وجو در متن پیام‌ها…">
            <select name="chat">
                <option value="">همه‌ی گفتگوها</option>
                @foreach ($chats as $c)
                    <option value="{{ $c->chat_id }}" @selected($chat == $c->chat_id)>{{ $c->title ?: $c->chat_id }} ({{ $c->n }})</option>
                @endforeach
            </select>
            <button class="btn primary sm">اعمال</button>
            <span class="grow"></span>
            <button type="button" class="btn danger sm" data-open="#delete-all">حذف همه‌ی پیام‌ها (به‌جز فیلترشده‌ها)</button>
        </form>
        <p class="muted small">{{ number_format($messages->total()) }} پیام</p>
        @forelse ($messages as $m)
            @include('partials.message', ['m' => $m, 'filtered' => isset($filteredIds[$m->id])])
        @empty
            <div class="empty">پیامی پیدا نشد.</div>
        @endforelse
        {{ $messages->links() }}
    </section>

    <dialog id="delete-all" class="dlg">
        <form method="post" action="{{ route('messages.delete-all') }}">
            @csrf
            <h3>حذف همه‌ی پیام‌ها</h3>
            <p>همه‌ی پیام‌های ذخیره‌شده حذف می‌شوند، <b>به‌جز پیام‌های فیلترشده</b> که در جدول جدا نگهداری می‌شوند. این کار برگشت‌پذیر نیست.</p>
            <label>برای تأیید بنویسید: <b>حذف</b>
                <input name="confirm" required autocomplete="off">
            </label>
            <div class="row">
                <button class="btn danger">حذف کن</button>
                <button type="button" class="btn ghost" data-close>انصراف</button>
            </div>
        </form>
    </dialog>
@endsection
