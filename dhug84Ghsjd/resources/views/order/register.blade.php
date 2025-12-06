@extends('layouts.app')
@section('title', 'ご注文内容確認')
@section('content')
<script>
	// 基本設定 ///////////////////////////////////////
	var tax = 1;
	var priceFlag = 1;
	var deliPrice = 500;
	var deliMaxPrice = 8000;
	var deliDisp = 0;
</script>
<div class="lmf-container">
	<div class="lmf-title_block tall">
		<h1 class="title">ご注文内容確認</h1>
	</div>
	<main class="lmf-main_contents">
		<section class="lmf-content">
			<form id="order_regist">
				<input type="hidden" name="user_id" value="{{ $user_id }}">
				@csrf
				<div class="lm-form_block lmf-white_block">
					<h2 class="lmf-title_sub">注文内容</h2>
					<ul class="lmf-item_list vartical">
						@foreach ($products as $product)
						<li data-cate="cate2">
							<div class="item_wrap">
								<figure class="item_fig">
									<img src="{{ asset('storage/' . $product->product_image) }}" alt="" />
								</figure>
								<div class="item_info">
									<span class="item_name">{{ $product->product_name }}</span>
									<div class="item_cartin">
										<b class="item_price">{{ number_format($product->price_with_tax) }}円<small class="tax">(税込)</small></b>
										<button class="del">削除</button> <button type="button" class="minus">－</button><input data-name="{{ $product->product_name }}" data-pid="{{ $product->product_code }}" data-price="{{ $product->price }}" name="item_number_{{ $product->id }}" type="text" value="{{$product->quantity}}"><button type="button" class="plus">＋</button>
									</div>
								</div>
							</div>
						</li>
						@endforeach
					</ul>
					<div class="lmf-order_total">
						<b class="label">送料</b><b class="item_price shipping_price">{{ number_format($shippingFee) }}円</b>
					</div>
					<div class="lmf-order_total">
						<b class="label">合計</b><span class="quantity">[{{$totalQuantity}}点]</span><b class="item_price grand_total_price">{{ number_format($grandTotalTaxIncluded) }}円<small class="tax">(税込)</small></b>
					</div>
					<div class="lmf-btn_box btn_gy">
						<button type="button">追加注文</button>
					</div>
				</div>
				<div class="lm-form_block lmf-white_block">
					<dl class="lmf-form_box">
						<dt>備考</dt>
						<dd><textarea name="memo" rows="3"></textarea></dd>
					</dl>

					<p class="mT20">下記ボタンを押すとトークに送信されます。<br>内容をご確認の上ボタンを押してください。</p>
					<div class="lmf-btn_box btn_red mT20">
						<button type="submit">注文する</button>
					</div>


				</div>
			</form>
		</section>
	</main>
</div><!-- /.lmf-container -->
<script>
	$(document).ready(function() {
		$('#order_regist').submit(function(event) {
			event.preventDefault(); // デフォルトのフォーム送信を防ぐ

			let formData = new FormData(this); // フォームデータを取得
			let submitBtn = $('#submitBtn');

			// ボタンを無効化して処理中表示
			submitBtn.prop('disabled', true).text('送信中...');

			$.ajax({
				url: "{{ route('order.store') }}", // 送信先（Laravelのルート）
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				headers: {
					'X-CSRF-TOKEN': $('input[name=_token]').val() // CSRFトークンをセット
				},
				success: function(response) {
					alert("注文が正常に送信されました！");
					// ローカルストレージを削除
					localStorage.removeItem('cartData');
					$('#order_regist')[0].reset(); // フォームをリセット
					let liffOrderUrl = `https://liff.line.me/{{ env('LIFF_ID_ORDER_HISTORY') }}`;
					window.location.href = liffOrderUrl;

				},
				error: function(xhr) {
					alert("エラーが発生しました: " + xhr.responseJSON.message);
				},
				complete: function() {
					submitBtn.prop('disabled', false).text('トークに送信'); // ボタンを元に戻す
				}
			});
		});

		// 追加注文
		$(document).ready(function() {
			$('#order_regist .btn_gy button').click(function() {
				let cartData = [];

				$('.lmf-item_list li').each(function() {
					let item = {
						product_id: $(this).find('input').data('pid'),
						name: $(this).find('.item_name').text(),
						price: $(this).find('input').data('price'),
						quantity: $(this).find('input').val(),
						image: $(this).find('img').attr('src'),
					};
					cartData.push(item);
				});

				// localStorage にカート情報を保存
				localStorage.setItem('cartData', JSON.stringify(cartData));

				// 追加注文ページへ遷移
				window.location.href = "{{ route('order.list') }}";
			});
		});

		// 削除ボタンをクリックしたときの処理
		$('.del').click(function(event) {
			event.preventDefault(); // デフォルトの動作を防ぐ

			let cate2Element = $(this).closest('li[data-cate="cate2"]'); // 直近の data-cate="cate2" を持つ li を取得
			cate2Element.remove(); // 画面から削除

			updateTotalPrice(); // 合計金額を更新
		});

		// 入力欄が変更されたら合計金額を更新
		$('.item_cartin input').on('change', function() {
			updateTotalPrice();
		});

		// 合計金額を更新する関数
		function updateTotalPrice() {
			let total = 0;
			let totalQuantity = 0;

			$('.lmf-item_list li').each(function() {
				let price = $(this).find('input').data('price');
				let quantity = parseInt($(this).find('input').val());

				if (!isNaN(quantity)) {
					total += price * quantity;
					totalQuantity += quantity;
				}
			});

			// 送料の計算（20,000円以下なら770円）
			let shippingFee = (total <= 20000) ? 770 : 0;
			// 税込合計（10%）+ 送料
			let grandTotalTaxIncluded = Math.round(total * 1.1) + shippingFee;

			$('.lmf-order_total .quantity').text("[" + totalQuantity + "点]");
			$('.shipping_price').text(shippingFee.toLocaleString() + "円");
			$('.grand_total_price').text(grandTotalTaxIncluded.toLocaleString() + "円");
		}

		// プラスボタン（＋）をクリック
		// $(document).off('click', '.plus').on('click', '.plus', function(event) {
		// 	event.preventDefault();
		// 	let inputField = $(this).siblings('input');
		// 	let currentQuantity = parseInt(inputField.val());

		// 	if (!isNaN(currentQuantity)) {
		// 		inputField.val(currentQuantity + 1);
		// 		updateTotalPrice();
		// 	}
		// });

		// // マイナスボタン（－）をクリック
		// $(document).off('click', '.minus').on('click', '.minus', function(event) {
		// 	event.preventDefault();
		// 	let inputField = $(this).siblings('input');
		// 	let currentQuantity = parseInt(inputField.val());

		// 	if (!isNaN(currentQuantity) && currentQuantity > 1) {
		// 		inputField.val(currentQuantity - 1);
		// 		updateTotalPrice();
		// 	}
		// });

		// 入力欄の値が変更されたときに合計金額を更新
		$('.item_cartin input').on('input', function() {
			let newValue = parseInt($(this).val());
			if (isNaN(newValue) || newValue < 1) {
				$(this).val(1); // 1未満は1にリセット
			}
			updateTotalPrice();
		});
	});
</script>
@endsection