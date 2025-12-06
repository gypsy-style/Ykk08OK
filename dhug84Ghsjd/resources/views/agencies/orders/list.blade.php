@extends('agencies.layouts.app')

@section('title', '注文詳細')

@section('content')
<section class="lma-content">
	<div class="lma-main_head">
		<div class="lma-title_block">
			<h2>発注</h2>
		</div>
	</div>
	<div class="lma-content_block nobg">
		<form action="{{route('agencies.orders.confirmation')}}" method="post">
			@csrf

			<ul class="lma-item_list product">
				@foreach ($categories as $category)
				@forelse ($category->products as $product)

				<li>
					<div class="lma-product_box">
						<div class="product_thumb">
							<img src="{{ asset('storage/' . $product->product_image) }}" alt="">
						</div>
						<div class="product_info">
							<div class="modifi_btns">
								<div class="lma-select_box">
									@php
									$lots = explode("\n", $product->lot);
									@endphp
									数量 <select name="item_number_{{ $product->id }}" id="item_number_{{ $product->id }}" >
										<option value="">選択してください</option>
										@foreach ($lots as $lot)
										<option value="{{ trim($lot) }}">{{ trim($lot) }}</option>
										@endforeach
									</select>
								</div>
							</div>
							<p class="cate_box"><span class="cate">{{$category->name}}</span></p>
							<h3 class="name">{{ $product->product_name }}</h3>
							<div class="price_box">
								<p class="prices"><span class="volume">[{{ $product->volume }}]</span><b class="price01">価格 {{ $product->price }}円</b></p>
								<p class="prices"><b class="price02">卸価格 {{ $product->wholesale_price }}円</b></p>
							</div>
						</div>
					</div>
				</li>
				@endforeach
				@endforeach

			</ul>
			<p class="lma-btn_box btn_wide"><button type="submit">注文する</button></p>

		</form>
	</div>
</section>
@endsection