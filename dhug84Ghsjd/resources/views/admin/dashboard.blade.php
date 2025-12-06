{{-- resources/views/admin/products/create.blade.php --}}
@extends('admin.layouts.app')

@section('title', 'ダッシュボード')
@section('content')

<section class="lma-content flex">
			<div class="lma-title_block center">
				<h2 class="color2">注文履歴</h2>
			</div>
			<div class="lma-content_block dashboard_order col20">
				<div class="process_box">
					<em class="label">代理店処理済</em>
					<b class="number color__sky">
						<a href="{{ route('admin.orders.index', ['status' => 2]) }}"><span class="num">{{ $statusCounts[2] }}</span></a><small class="unit">件</small>
					</b>
				</div>
			</div>
			<div class="lma-content_block dashboard_order col20">
				<div class="process_box">
					<em class="label">本部処理済</em>
					<b class="number">
						<a href="{{ route('admin.orders.index', ['status' => 3]) }}"><span class="num">{{ $statusCounts[3] }}</span></a><small class="unit">件</small>
					</b>
				</div>
			</div>
			<div class="lma-content_block dashboard_order col20">
				<div class="process_box">
					<em class="label">発送待ち</em>
					<b class="number color__sky">
						<a href="{{ route('admin.orders.index', ['status' => 5]) }}"><span class="num">{{ $statusCounts[5] }}</span></a><small class="unit">件</small>
					</b>
				</div>
			</div>
			<div class="lma-content_block dashboard_order col20">
				<div class="process_box">
					<em class="label">発送済み</em>
					<b class="number color__sky">
						<a href="{{ route('admin.orders.index', ['status' => 6]) }}"><span class="num">{{ $statusCounts[6] }}</span></a><small class="unit">件</small>
					</b>
				</div>
			</div>
			<div class="lma-content_block dashboard_order col20">
				<div class="process_box">
					<em class="label">保留</em>
					<b class="number color__sky">
						<a href="{{ route('admin.orders.index', ['status' => 4]) }}"><span class="num">{{ $statusCounts[4] }}</span></a><small class="unit">件</small>
					</b>
				</div>
			</div>
			<div class="lma-btn_box center">
				<a href="{{ route('admin.orders.index') }}">受注一覧</a>
			</div>
		</section>

<section class="lma-content flex">
			
			<div class="lma-content_block dashboard_store col50">
				<div class="store_box">
					<em class="label">代理店数</em>
					<b class="number color__sky"><span class="num">{{ $data['agencyCount'] }}</span><small class="unit">店舗</small></b>
				</div>
				<p class="lma-btn_box"><a href="{{ route('admin.agencies.index') }}">代理店一覧</a></p>
			</div>
			<div class="lma-content_block dashboard_user col50">
				<div class="user_box">
					<em class="label">加盟店数</em>
					<b class="number color__pk"><span class="num">{{ $data['merchantCount'] }}</span><small class="unit">名</small></b>
				</div>
				<p class="lma-btn_box"><a href="{{ route('admin.merchants.index') }}">加盟店一覧</a></p>
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
					<li class="prev"><a href="{{ route('admin.dashboard', ['month' => $nextMonth]) }}">次月</a></li>
					<li class="next"><a href="{{ route('admin.dashboard', ['month' => $prevMonth]) }}">先月</a></li>
				</ul>
			</div>
		</section>
@endsection