<div class="records_caption">
    <h2 class="lma-title_bar sky"><em class="label">サロン別商品売上（税込・{{ \Carbon\Carbon::parse($productMonth . '-01')->format('Y年n月') }}）</em></h2>
</div>
<div class="records_table">
    <table class="lma-detail_tbl">
        <tbody>
            <tr>
                <th>サロン名</th>
                @foreach ($productSales['products'] as $product)
                <td>{{ $product['name'] }}</td>
                @endforeach
                <td>合計</td>
            </tr>
            @forelse ($productSales['rows'] as $row)
            <tr>
                <th>{{ $row['name'] }}@if ($row['deleted'])（削除済み）@endif</th>
                @foreach ($productSales['products'] as $product)
                <td>{{ number_format($row['byProduct'][$product['id']]) }}</td>
                @endforeach
                <td>{{ number_format($row['total']) }}</td>
            </tr>
            @empty
            <tr>
                <th>加盟店なし</th>
                <td colspan="{{ count($productSales['products']) + 1 }}"></td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
<ul class="lma-pnavi_list clearfix">
    <li class="prev"><a href="#" data-month="{{ $productPrevMonth }}">先月</a></li>
    {{-- 当月を表示中は次月へ進めない --}}
    @if ($productHasNextMonth)
    <li class="next"><a href="#" data-month="{{ $productNextMonth }}">次月</a></li>
    @endif
</ul>
