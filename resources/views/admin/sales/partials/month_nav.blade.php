<div class="lma-content_block nobg" style="width:100%;">
    <ul class="lma-pnavi_list clearfix">
        <li class="prev"><a href="{{ route('admin.sales.index', ['month' => $prevMonth]) }}">先月</a></li>
        {{-- 当月を表示中は次月へ進めない --}}
        @if ($isFixedMonth)
            <li class="next"><a href="{{ route('admin.sales.index', ['month' => $nextMonth]) }}">次月</a></li>
        @endif
    </ul>
</div>
