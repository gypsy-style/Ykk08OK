<div class="records_caption">
    <h2 class="lma-title_bar sky"><em class="label">代理店別新規加盟店数（{{ \Carbon\Carbon::parse($months[0] . '-01')->format('Y年n月') }}〜{{ \Carbon\Carbon::parse($month . '-01')->format('Y年n月') }}）</em></h2>
</div>
<div class="records_table">
    <ul class="lma-pnavi_list clearfix analytics_pnavi">
        <li class="prev"><a href="#" data-month="{{ $prevMonth }}">先月</a></li>
        {{-- 当月を表示中は次月へ進めない --}}
        @if ($hasNextMonth)
        <li class="next"><a href="#" data-month="{{ $nextMonth }}">次月</a></li>
        @endif
    </ul>
    <table class="lma-detail_tbl analytics_tbl">
        <tbody>
            <tr>
                <th>代理店名</th>
                @foreach ($months as $m)
                <td>{{ \Carbon\Carbon::parse($m . '-01')->format('n月') }}</td>
                @endforeach
            </tr>
            @foreach ($newMerchants['rows'] as $row)
            <tr>
                {{-- 代理店なしの行はリンク先が無い --}}
                <th>@if ($row['id'])<a href="{{ route('admin.analytics.agency', ['agency' => $row['id'], 'month' => $month]) }}">{{ $row['name'] }}</a>@else{{ $row['name'] }}@endif</th>
                @foreach ($months as $m)
                <td>{{ number_format($row['byMonth'][$m]) }}</td>
                @endforeach
            </tr>
            @endforeach
            <tr>
                <th>合計</th>
                @foreach ($months as $m)
                <td>{{ number_format($newMerchants['totals'][$m]) }}</td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>
