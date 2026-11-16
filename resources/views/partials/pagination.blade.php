@if ($paginator->hasPages())
<nav class="pager" aria-label="صفحه‌بندی">
    @if ($paginator->onFirstPage())<span class="off">قبلی</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>@endif
    @foreach ($elements as $element)
        @if (is_string($element))<span class="off">…</span>@endif
        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())<span class="cur" aria-current="page">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
            @endforeach
        @endif
    @endforeach
    @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>@else<span class="off">بعدی</span>@endif
</nav>
@endif
