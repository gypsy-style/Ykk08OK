@extends('admin.layouts.app')

@section('title', '管理画面 [売上管理]')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>売上管理</h2>
        </div>
    </div>

    <div class="lma-content_block dashboard_records" style="width:100%;">
        <div class="record_block">
            <div class="records_caption">
                <h2 class="lma-title_bar sky"><em class="label">{{ \Carbon\Carbon::parse($month . '-01')->format('Y年m月') }}</em></h2>
            </div>
            <div class="records_table">
                <dl class="records_list">
                    @forelse ($productSales as $row)
                    <dt style="width:inherit;">{{ $row->product_name }}</dt>
                    <dd>
                        <div class="inner"><span class="num">{{ (int) $row->total_quantity }}件</span><em class="price">{{ number_format($row->total_amount ?? 0) }}円</em></div>
                    </dd>
                    @empty
                    <dt>売上</dt>
                    <dd>
                        <div class="inner"><span class="num">0件</span><em class="price">0円</em></div>
                    </dd>
                    @endforelse
                    <dt>送料</dt>
                    <dd>
                        <div class="inner"><span class="num">{{ $shippingFeeCount }}件</span><em class="price">{{ number_format($headquartersProcessed->shipping_fee ?? 0) }}円</em></div>
                    </dd>
                    <dt>合計</dt>
                    <dd>
                        <div class="inner"><span class="num"></span><em class="price">{{ number_format($grandTotal) }}円</em></div>
                    </dd>
                </dl>
            </div>

        </div>

    </div>

    <div class="lma-content_block staff nobg">
        <ul class="lma-user_list store">
            @forelse ($merchantSales as $m)
                <li>
                    <div class="lma-user_box">
                        <div class="user_info">
                            <h3 class="name">{{ $m->merchant_name }}</h3>
                            <p class="line_id">{{ $m->agency_name ?? '代理店未設定' }}　会員ランク{{ $m->member_rank ?? '-' }}</p>
                        </div>
                        <div class="lma-select_box"></div>
                        <div class="lma-btn_box btn_list">
                            <a href="{{ route('admin.sales.invoice', ['merchant' => $m->merchant_id, 'month' => $month]) }}" target="_blank" rel="noopener" class="">PDFを表示</a>
                            <a href="{{ route('admin.sales.show', ['merchant' => $m->merchant_id, 'month' => $month]) }}" class="">詳細</a>
                        </div>
                    </div>
                </li>
            @empty
                <li>
                    <div class="lma-user_box">
                        <div class="user_info">
                            <p class="line_id">この月に売上があった店舗はありません。</p>
                        </div>
                    </div>
                </li>
            @endforelse
        </ul>
    </div>

    <div class="lma-content_block nobg" style="width:100%;">
        <ul class="lma-pnavi_list clearfix">
            <li class="next"><a href="{{ route('admin.sales.index', ['month' => $prevMonth]) }}">先月</a></li>
            <li class="prev"><a href="{{ route('admin.sales.index', ['month' => $nextMonth]) }}">次月</a></li>
        </ul>
    </div>
</section>
@endsection