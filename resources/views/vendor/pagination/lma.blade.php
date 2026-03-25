@if ($paginator->hasPages())
<div class="lma-pagination">
    {{-- 最初のページ --}}
    @if ($paginator->onFirstPage())
        <span class="disabled">«</span>
        <span class="disabled">‹</span>
    @else
        <a href="{{ $paginator->url(1) }}">«</a>
        <a href="{{ $paginator->previousPageUrl() }}">‹</a>
    @endif

    {{-- ページ番号 --}}
    @foreach ($elements as $element)
        @if (is_string($element))
            <span class="dots">{{ $element }}</span>
        @endif

        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="current">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="inactive">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- 最後のページ --}}
    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}">›</a>
        <a href="{{ $paginator->url($paginator->lastPage()) }}">»</a>
    @else
        <span class="disabled">›</span>
        <span class="disabled">»</span>
    @endif
</div>
@endif
