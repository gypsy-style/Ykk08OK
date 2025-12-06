{{-- resources/views/admin/products/create.blade.php --}}
@extends('agencies.layouts.app')

@section('title', 'ダッシュボード')
@section('content')

<section class="lma-content flex">
			<div class="lma-content_block dashboard_store col50">
				<div class="store_box">
					<em class="label">本日の受注</em>
					<b class="number color__sky">
						<span class="num">{{ $data['todayOrderCount'] }}</span><small class="unit">件</small>
						<span class="num">{{ number_format($data['todayTotalPriceSum'], 0) }}</span><small class="unit">円</small>
					</b>
				</div>
				<p class="lma-btn_box"><a href="{{ route('agencies.orders.index') }}">受注一覧</a></p>
			</div>
			<div class="lma-content_block dashboard_user col50">
				<div class="user_box">
					<em class="label">加盟店数</em>
					<b class="number color__pk"><span class="num">{{ $data['merchantCount'] }}</span><small class="unit">名</small></b>
				</div>
				<p class="lma-btn_box"><a href="{{ route('agencies.merchants.index') }}">加盟店一覧</a></p>
			</div>
			<div class="lma-content_block dashboard_records">
				<div class="record_block">
					<div class="records_caption">
						<h2 class="lma-title_bar sky"><em class="label">{{ \Carbon\Carbon::parse($month . '-01')->format('Y年m月') }}</em></h2>
					</div>
					<div class="records_table">
						<dl class="records_list">
							<dt>売上</dt>
							<dd><div class="inner"><span class="num">{{ $headquartersProcessed->order_count }}件</span><em class="price">{{ number_format($headquartersProcessed->total_price ?? 0) }}円</em></div></dd>
							<dt>送料</dt>
							<dd><div class="inner"><span class="num">{{$shippingFeeCount}}件</span><em class="price">{{ number_format($headquartersProcessed->shipping_fee ?? 0) }}円</em></div></dd>
						</dl>
					</div>
				</div>
			</div>
			<div class="lma-content_block nobg">
				<ul class="lma-pnavi_list clearfix">
				<li class="prev"><a href="{{ route('agencies.dashboard', ['month' => $nextMonth]) }}">次月</a></li>
				<li class="next"><a href="{{ route('agencies.dashboard', ['month' => $prevMonth]) }}">先月</a></li>
				</ul>
			</div>
		</section>
@endsection