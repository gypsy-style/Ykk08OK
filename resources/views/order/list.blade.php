@extends('layouts.app')
@section('title', '注文ページ')
@section('content')


<div class="lmf-container">

	<script>
		// 基本設定 ///////////////////////////////////////
		var tax = 1;
		var priceFlag = 1;
		var deliPrice = 500;
		var deliMaxPrice = 8000;
		var deliDisp = 0;
	</script>

	<div class="lmf-order_fixbar">
		<div class="fix_contents">
			<em class="fix_price_txt">合計 <span class="fix_price">0</span>円<small class="tax">(税込)</small></em>
			<p class="fix_btn_box">
				<!-- <label for="send_modal" class="fix_btn" style="display: none;">注文内容を確認</span></label> -->
				<!-- <a href="#" class="fix_btn" id="confirm_button">注文内容を確認</a> -->
				<label for="send_modal" class="fix_btn" id="confirm_button">注文内容を確認</label>
			</p>
		</div>
		<div class="fix_tabs">
			<div class="thumb_splide splide" role="group" aria-label="タブ">
				<div class="splide__track">
					<div class="splide__list">
						@foreach ($categories as $category)
						<div class="splide__slide"><span class="label">{{$category->name}}</span></div>
						@endforeach
					</div>
				</div>
			</div>
		</div>
	</div>

	<main class="lmf-main_contents">
		<div class="lmf-itemlist_block">
			<form method="POST" action="{{route('order.register')}}" id="send_form">
				@csrf
				<div class="main_splide splide" role="group" aria-label="メイン">
					<div class="splide__track">
						<div class="splide__list">
							@foreach ($categories as $category)
							<div class="splide__slide">
								<ul class="lmf-item_list set_input_list vartical">
									@forelse ($category->products as $product)
									<li data-cate="cate2">
										<div class="item_wrap">
											<figure class="item_fig">
												<img src="{{ asset('storage/' . $product->product_image) }}" alt="no image" />
											</figure>
											<div class="item_info">
												<span class="item_name">{{ $product->set_sale_name ?: $product->product_name }}</span>
												<div class="item_cartin">
													@php
														$defaultTax = (int) round(($product->price ?? 0) * 1.1);
														$rank1Tax = $product->price_1 !== null ? (int) round($product->price_1 * 1.1) : null;
														$rank2Tax = $product->price_2 !== null ? (int) round($product->price_2 * 1.1) : null;
														$rank3Tax = $product->price_3 !== null ? (int) round($product->price_3 * 1.1) : null;
													@endphp
													<b class="item_price"
														data-price-default="{{ $defaultTax }}"
														data-price-1="{{ $rank1Tax ?? '' }}"
														data-price-2="{{ $rank2Tax ?? '' }}"
														data-price-3="{{ $rank3Tax ?? '' }}"
													>{{ number_format($defaultTax) }}円<small class="tax">(税込)</small></b>
													<button class="minus" type="button">－</button><input
														data-name="{{ $product->product_name }}" data-pid="{{ $product->product_code }}"
														data-price="{{ $defaultTax }}"
														data-price-default="{{ $defaultTax }}"
														data-price-1="{{ $rank1Tax ?? '' }}"
														data-price-2="{{ $rank2Tax ?? '' }}"
														data-price-3="{{ $rank3Tax ?? '' }}"
														name="item_number_{{ $product->id }}" type="text" value="0"><button
														class="plus" type="button">＋</button>
												</div>
											</div>
										</div>
									</li>
									@empty
									<li>商品がありません</li>
									@endforelse
								</ul>
							</div><!-- splide__slide -->
							@endforeach

						</div>
					</div>
				</div><!-- .main_splide -->
				<input type="hidden" name="access_token" value="{{ app()->environment('local') ? 'dummy_token' : '' }}" id="access_token">
			</form>
		</div>
	</main>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			var thumbnails = new Splide('.thumb_splide', {
				isNavigation: true,
				arrows: false,
				focus: 'center',
				autoWidth: true,
				pagination: false,
				gap: 15,
				dragMinThreshold: {
					mouse: 4,
					touch: 10,
				},
			});
			var main = new Splide('.main_splide', {
				pagination: false,
				arrows: false,
			});

			main.sync(thumbnails);
			main.mount();
			thumbnails.mount();
		});
		// 追加注文
		document.addEventListener('DOMContentLoaded', function() {
			let cartData = JSON.parse(localStorage.getItem('cartData'));
			let total = 0; // 合計金額
			let totalQuantity = 0; // 合計数量

			if (cartData && cartData.length > 0) {
				cartData.forEach(item => {
					let inputField = document.querySelector(`input[data-pid="${item.product_id}"]`);
					if (inputField) {
						inputField.value = item.quantity;
						total += item.price * item.quantity; // 合計金額を計算
						totalQuantity += item.quantity; // 合計数量を計算

						// **該当する li に is_selected クラスを追加**
						let listItem = inputField.closest('li');
						if (listItem) {
							listItem.classList.add('is_selected');
						}
					}
				});

				// **合計金額を表示**
				document.querySelector('.fix_price').textContent = total.toLocaleString();

				// **注文内容の合計数量も更新**
				let quantityElement = document.querySelector('.lmf-order_total .quantity');
				if (quantityElement) {
					quantityElement.textContent = `[${totalQuantity}点]`;
				}

				let totalPriceElement = document.querySelector('.lmf-order_total .item_price:last-of-type');
				if (totalPriceElement) {
					totalPriceElement.textContent = total.toLocaleString() + "円";
				}
			}

			// 取得後、localStorage をクリア（必要なら削除）
			localStorage.removeItem('cartData');
		});
	</script>
	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// 既存のSplideの設定コード...

			// フォーム送信の設定
			const confirmButton = document.getElementById('confirm_button'); // ボタンを取得
			const sendForm = document.getElementById('send_form'); // フォームを取得

			confirmButton.addEventListener('click', function() {
				sendForm.submit(); // フォームを送信
			});
		});
	</script>


	<!-- <div class="lmf-modal_layer"></div>
		<div class="lmf-modal_wrap">
			<div class="lmf-modal_content" id="modal_expired">
				<div class="modal_close_btn"><button>&times;</button></div>
				<div class="inner">
					<form action="#" id="send_form">

						<h2>注文内容</h2>
						<p class="center">ご注文内容をご確認ください。</p>
						<div class="item_area">
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
							<textarea name="item" rows="2" readonly></textarea>
						</div>
						<div class="form_btn_box confirm">
							<p>下記ボタンを押すとトークに送信されます。<br>内容をご確認の上ボタンを押してください。</p>
							<input type="submit" value="トークに送信" disabled>
						</div>

					</form>
				</div>
			</div>
		</div> -->
	<!-- /.modal_wrap -->



</div><!-- /.lmf-container -->


@endsection
@push('scripts')
@if (!app()->environment('local'))
	<script>
		window.LIFF_ID = "{{ config('app.order_liff_id') }}";
	</script>
	@vite(['resources/js/liff.js'])
	<script>
		// liff.js の初期化や設定をここに記述
		// LIFF initialization code
	</script>
@endif
@endpush