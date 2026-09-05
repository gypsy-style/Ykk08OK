@extends('admin.layouts.app')

@section('title', '管理画面 [' . $detail['merchant']['name'] . ' の分析]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>{{ $detail['merchant']['name'] }}@if ($detail['merchant']['deleted'])（削除済み）@endif</h2>
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
                            <th>累計売上（税込）</th>
                            <td>{{ number_format($detail['grandTotal']) }} 円</td>
                        </tr>
                        <tr>
                            <th>平均月売上（税込）</th>
                            <td>{{ number_format($detail['averageMonthly']) }} 円</td>
                        </tr>
                        <tr>
                            <th>集計期間</th>
                            <td>
                                @if ($detail['firstMonth'] === null)
                                売上なし
                                @else
                                {{ \Carbon\Carbon::parse($detail['firstMonth'] . '-01')->format('Y年n月') }}〜{{ \Carbon\Carbon::now()->format('Y年n月') }}（{{ $detail['monthCount'] }}ヶ月）
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block">
            <div class="records_caption">
                <h2 class="lma-title_bar sky"><em class="label">月別商品売上（税込・{{ \Carbon\Carbon::parse($months[0] . '-01')->format('Y年n月') }}〜{{ \Carbon\Carbon::parse($month . '-01')->format('Y年n月') }}）</em></h2>
            </div>
            <div class="records_table">
                <ul class="lma-pnavi_list clearfix analytics_pnavi">
                    <li class="prev"><a href="{{ route('admin.analytics.salon', ['merchant' => $detail['merchant']['id'], 'month' => $prevMonth]) }}">先月</a></li>
                    {{-- 当月を表示中は次月へ進めない --}}
                    @if ($hasNextMonth)
                    <li class="next"><a href="{{ route('admin.analytics.salon', ['merchant' => $detail['merchant']['id'], 'month' => $nextMonth]) }}">次月</a></li>
                    @endif
                </ul>
                <table class="lma-detail_tbl analytics_tbl">
                    <tbody>
                        <tr>
                            <th>月</th>
                            @foreach ($detail['products'] as $product)
                            <td>{{ $product['name'] }}</td>
                            @endforeach
                            <td>合計</td>
                        </tr>
                        @foreach ($months as $m)
                        <tr>
                            <th>{{ \Carbon\Carbon::parse($m . '-01')->format('Y年n月') }}</th>
                            @foreach ($detail['products'] as $product)
                            <td>{{ number_format($detail['monthly'][$m]['byProduct'][$product['id']]) }}</td>
                            @endforeach
                            <td>{{ number_format($detail['monthly'][$m]['total']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block">
            <div class="records_caption">
                <h2 class="lma-title_bar sky"><em class="label">累計商品別売上（税込）</em></h2>
            </div>
            <div class="records_table">
                <table class="lma-detail_tbl analytics_tbl">
                    <tbody>
                        <tr>
                            <th>商品名</th>
                            <td>累計売上</td>
                        </tr>
                        @forelse ($detail['products'] as $product)
                        <tr>
                            <th>{{ $product['name'] }}</th>
                            <td>{{ number_format($product['total']) }}</td>
                        </tr>
                        @empty
                        <tr>
                            <th>売上なし</th>
                            <td></td>
                        </tr>
                        @endforelse
                        <tr>
                            <th>合計</th>
                            <td>{{ number_format($detail['grandTotal']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="lma-content_block nobg" style="width:100%;">
        <p class="lma-btn_box btn_wh btn_min"><a href="{{ route('admin.analytics', ['month' => $month]) }}">サロン分析へ戻る</a></p>
    </div>
</section>
@endsection

@push('head')
@include('admin.analytics._style')
@endpush
