@extends('admin.layouts.app')

@section('title', '管理画面 [サロン分析]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>サロン分析</h2>
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block">
            <div class="records_caption">
                <h2 class="lma-title_bar sky"><em class="label">サマリー</em></h2>
            </div>
            <div class="records_table">
                <table class="lma-detail_tbl analytics_tbl">
                    <tbody>
                        <tr>
                            <th>項目</th>
                            <td>内容</td>
                        </tr>
                        <tr>
                            <th>総加盟店数</th>
                            <td>{{ number_format($overview['merchantCount']) }} 件</td>
                        </tr>
                        <tr>
                            <th>累計売上（税込）</th>
                            <td>{{ number_format($overview['grandTotal']) }} 円</td>
                        </tr>
                        <tr>
                            <th>平均月売上（税込）</th>
                            <td>{{ number_format($overview['averageMonthly']) }} 円</td>
                        </tr>
                        <tr>
                            <th>集計期間</th>
                            <td>
                                @if ($overview['firstMonth'] === null)
                                売上なし
                                @else
                                {{ \Carbon\Carbon::parse($overview['firstMonth'] . '-01')->format('Y年n月') }}〜{{ \Carbon\Carbon::now()->format('Y年n月') }}（{{ $overview['monthCount'] }}ヶ月）
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block analytics_async" data-url="{{ route('admin.analytics.new_merchants') }}">
            @include('admin.analytics._new_merchants')
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block analytics_async" data-url="{{ route('admin.analytics.product_sales') }}">
            @include('admin.analytics._product_sales')
        </div>
    </div>
</section>
@endsection

@push('head')
@include('admin.analytics._style')
@include('admin.analytics._async_script')
@endpush
