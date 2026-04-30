@extends('admin.layouts.app')

@section('title', '店舗別受注詳細')

@section('content')

<section class="lma-content">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>店舗別受注詳細</h2>
        </div>
    </div>
    <div class="lma-content_block order_detail">
        <dl class="lma-orderinfo_list">
            <dt>受注月</dt>
            <dd>{{ \Carbon\Carbon::parse($month . '-01')->format('Y年m月') }}</dd>
            <dt>発注店舗</dt>
            <dd>{{ $merchant->name }}</dd>
            <dt>住所</dt>
            <dd>{{ $merchant->postal_code1 && $merchant->postal_code2 ? '〒' . $merchant->postal_code1 . '-' . $merchant->postal_code2 . ' ' : '' }}{{ $merchant->address }}</dd>
            <dt>電話番号</dt>
            <dd>{{ $merchant->phone }}</dd>
        </dl>

        <div class="lma-detail_wrap" style="margin-top:20px;">
            @if (count($productAgg) > 0)
                <table class="lma-detail_tbl">
                    <tbody>
                        @foreach ($productAgg as $row)
                            <tr>
                                <th>{{ $row['name'] }}</th>
                                <td>{{ $row['quantity'] }}</td>
                                <td>{{ number_format($row['amount']) }}円</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th>送料</th>
                            <td></td>
                            <td>{{ number_format($monthShippingFee) }}円</td>
                        </tr>
                        <tr>
                            <th>消費税</th>
                            <td></td>
                            <td>{{ number_format($monthTaxAmount) }}円</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th>合計</th>
                            <td>{{ $monthTotalQuantity }}</td>
                            <td>{{ number_format($monthGrandTotal) }}円</td>
                        </tr>
                    </tfoot>
                </table>

                
            @else
                <p style="padding:20px; text-align:center;">この月にこの店舗の受注はありません。</p>
            @endif
        </div>
        <div class="lma-copy_wrap" style="margin-top:10px;">
                    <pre id="order-copy-text" style="border:1px solid #ddd; border-radius:4px; padding:12px; font-size:13px; line-height:1.8; white-space:pre-wrap; word-break:break-all;">{{ $copyText }}</pre>
                    <button type="button" id="copy-btn" style="margin-top:8px; padding:6px 16px; background:#4d6684; color:#fff; border:none; border-radius:4px; font-size:13px; cursor:pointer;">テキストをコピー</button>
                </div>
    </div>

    <div class="lma-content_block nobg" style="width:100%;">
        <ul class="lma-pnavi_list clearfix">
            <li class="next"><a href="{{ route('admin.sales.show', ['merchant' => $merchant->id, 'month' => $prevMonth]) }}">先月</a></li>
            <li class="prev"><a href="{{ route('admin.sales.show', ['merchant' => $merchant->id, 'month' => $nextMonth]) }}">次月</a></li>
        </ul>
    </div>

    <div class="lma-btn_box">
        <a href="{{ route('admin.sales.index', ['month' => $month]) }}" class="bl">前に戻る</a>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var copyBtn = document.getElementById('copy-btn');
        if (!copyBtn) return;
        copyBtn.addEventListener('click', function() {
            var target = document.getElementById('order-copy-text');
            if (!target) return;
            navigator.clipboard.writeText(target.innerText).then(function() {
                copyBtn.textContent = 'コピーしました';
                setTimeout(function() { copyBtn.textContent = 'テキストをコピー'; }, 2000);
            });
        });
    });
</script>
@endsection
