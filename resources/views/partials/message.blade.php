@php($types = ['user' => 'خصوصی', 'bot' => 'ربات', 'group' => 'گروه', 'supergroup' => 'سوپرگروه', 'channel' => 'کانال'])
<article class="msg {{ $m->is_outgoing ? 'out' : '' }}">
    <div class="msg-av" style="--h: {{ crc32((string) $m->chat_id) % 360 }}">{{ mb_substr($m->chat_title ?: '؟', 0, 1) }}</div>
    <div class="msg-b">
        <div class="msg-h">
            <b>{{ $m->chat_title ?: $m->chat_id }}</b>
            @if ($m->chat_type)<span class="tag">{{ $types[$m->chat_type] ?? $m->chat_type }}</span>@endif
            @if (! empty($filtered))<span class="tag green">فیلترشده</span>@endif
            <time title="{{ $m->sent_at }}">{{ $m->sent_at?->diffForHumans() }}</time>
        </div>
        @if ($m->sender_name && $m->sender_name !== $m->chat_title)<div class="msg-from">{{ $m->sender_name }}</div>@endif
        <div class="msg-text">{!! isset($highlight) ? $highlight($m->text) : nl2br(e($m->text)) !!}@if ($m->media_type)<span class="media">📎 {{ $m->media_type }}</span>@endif
        @if (! $m->text && ! $m->media_type)<span class="muted">(بدون متن)</span>@endif</div>
    </div>
</article>
