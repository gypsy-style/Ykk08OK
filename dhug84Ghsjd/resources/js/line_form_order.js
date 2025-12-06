/* 基本設定 */
//var tax = 1.1 ;
//var deliPrice = 500 ;
//var deliMaxPrice = 8000 ;

$(function () {



	/* 送料挿入 */
	if (deliDisp != '0') {
		$('input[name=deliprice]').val(deliPrice).show();
	}
	if (priceFlag != '0') {
		$('input[name=totalprice]').val('0').show();
	}

	/* 送信用商品エリア内textarea非表示 */
	$('.item_area textarea').hide();

	/* +- */
	var number;
	$('.minus').on('click', function () {
		console.log('minus')
		number = Number($(this).next('input').val());
		var input = $(this).next('input');
		if (number > 0) {
			input.val(number - 1).trigger('change');
		}
	});
	$('.plus').on('click', function () {
		number = Number($(this).prev('input').val());
		var input = $(this).prev('input');
		input.val(number + 1).trigger('change');
	});

	/* 商品名/JAN/価格格納イベント */
	$('.set_input_list input').on('change', function () {
		var pid = 'id' + $(this).data('pid');
		var product_name = $(this).data('name');
		var price = $(this).data('price');
		price = Math.round(parseInt(price) * tax);
		var val = $(this).val();
		const name = $(this).attr('name');
		const selector = '#send_form input[type="hidden"][name="' + name + '"]';
		if (val > 0) {
			$(this).parents('li').addClass('is_selected');
			// send_formの中に存在するかチェック
			
			if ($(selector).length > 0) {
				$(selector).val(val);
			} else {
				// 存在しなければhiddenにして追加
				const originalInput = $(this);
				const clonedInput = originalInput.clone().attr('type', 'hidden');
				$('#send_form').append(clonedInput);
			}
		} else {
			$(this).parents('li').removeClass('is_selected');

			//send_formの中にitem_number_が存在すれば削除
			if ($(selector).length > 0) {
				$(selector).remove();
			} 
		}
		//名前が同じなの探す
		var itemFlag = true;
		$('.item_area textarea').each(function (i) {
			var nameAry = $(this).val().split('□');
			if (nameAry[0] === pid) {
				itemFlag = false;
				if (val > 0) {
					if (priceFlag != '0') {
						$(this).val(pid + '□' + product_name + '：' + price.toLocaleString() + '円(税込)×' + val + '個＝' + Number(price * val).toLocaleString() + '円(税込)').show();
					} else {
						$(this).val(pid + '□' + product_name + '×' + val + '個').show();
					}
				} else {
					$(this).val('').hide();
				}
				return false;
			}
		});
		//同じのがなかったら新規に追加
		if (itemFlag) {
			$('.item_area textarea').each(function (i) {
				if ($(this).val() === '') {
					if (priceFlag != '0') {
						$(this).val(pid + '□' + product_name + '：' + price.toLocaleString() + '円(税込)×' + val + '個＝' + Number(price * val).toLocaleString() + '円(税込)').show();
					} else {
						$(this).val(pid + '□' + product_name + '×' + val + '個').show();
					}
					return false;
				}
			});
		}
		unitTotal();
	});

	/* 送信イベント
	$('form').submit(function () {
		var items = '';
		$('textarea[name=item]').each(function(i){
			if( $(this).val() !== '' ){
				items += '\n■' + $(this).val();
			}
		});
		var deliprice = '';
		if( $('[name="deliprice"]').val() === '無料' ) {
			deliprice = '無料';
		} else {
			deliprice = $('[name="deliprice"]').val()+'円';
		}
		var totalprice = $('[name="totalprice"]').val();
		//お届け先ここから [現在送信情報になし]
		var name = '';
		var address = '';
		var tel = '';
		var delitxt = '';
		if( $('[name="name"]').val() !== '' ) {
			name = '名前：' + $('[name="name"]').val() + '\n';
		}
		if( $('[name="address"]').val() !== '' ) {
			address = '住所：' + $('[name="address"]').val() + '\n';
		}
		if( $('[name="tel"]').val() !== '' ) {
			tel = '電話番号：' + $('[name="tel"]').val() + '\n';
		}
		if( name !== '' || address !== '' || tel !== '' ) {
			delitxt = '\n-------------\n[お届け先情報]\n' + name + address + tel ;
		}
		var payment = '';
		if( $('[name="payment"]').is(':checked') ) {
			payment = $('[name="payment"]:checked').val();
		}
		var comment = '';
		if( $('[name="comment"]').val() !== '' ) {
			comment = $('[name="comment"]').val();
		}
		var msg = `下記内容で注文お願いいたします\n-------------${items}\n`;
		if(comment != ''){
			msg = msg + `\n備考：${comment}`;
		}
		if(payment != ''){
			msg = msg + `\n支払い方法：${payment}`;
		}
		if(deliDisp != '0'){
			msg = msg + `\n送料：${deliprice}`;
		}
		if(priceFlag != '0'){
			msg = msg + `\n合計金額：${totalprice}円(税込)`;
		}
		if(deliDisp != '0' || priceFlag != '0'){
			msg = msg + `\n-------------`;
		}
		console.log(''+msg);
		//sendText(msg);
		return false;
	});*/

});

//価格計算
function unitTotal() {
	//商品合計計算
	var sumPrice = 0;
	var sumCount = 0;
	$('.set_input_list input').each(function () {
		var price = $(this).data('price');
		var val = $(this).val();
		sumPrice = sumPrice + parseInt(price * val);
		sumCount = sumCount + parseInt(val);
	});
	sumPrice = Math.round(sumPrice * tax);
	console.log(sumPrice);

	//配送料金計算
	if (deliDisp != '0') {
		if (sumPrice < deliMaxPrice && deliMaxPrice != 0) {
			$('input[name=deliprice]').val(deliPrice.toLocaleString());
			$('.delivery_price_block .yen').show();
		} else {
			$('input[name=deliprice]').val('無料');
			$('.delivery_price_block .yen').hide();
		}
	}
	if (priceFlag != '0') {
		$('.fix_price').text(sumPrice.toLocaleString());
		$('input[name=totalprice]').val(sumPrice.toLocaleString());
	} else {
		$('.fix_count').text(sumCount);
	}

	if (sumCount > 0) {
		$('.form_btn_box [type="submit"]').prop('disabled', false);
	} else {
		$('.form_btn_box [type="submit"]').prop('disabled', true);
	}
}

//スムーズスクロール:non jquery
var Ease = {
	easeInOut: t => t < .5 ? 4 * t * t * t : (t - 1) * (2 * t - 2) * (2 * t - 2) + 1
}
var duration = 500;
window.addEventListener('DOMContentLoaded', () => {
	var smoothScrollTriggers = document.querySelectorAll('a[href^="#"]');
	smoothScrollTriggers.forEach(function (smoothScrollTrigger) {
		smoothScrollTrigger.addEventListener('click', function (e) {
			var href = smoothScrollTrigger.getAttribute('href');
			var currentPostion = document.documentElement.scrollTop || document.body.scrollTop;
			var targetElement = document.getElementById(href.replace('#', ''));
			if (targetElement) {
				e.preventDefault();
				e.stopPropagation();
				var targetPosition = window.pageYOffset + targetElement.getBoundingClientRect().top - 20;
				var startTime = performance.now();
				var loop = function (nowTime) {
					var time = nowTime - startTime;
					var normalizedTime = time / duration;
					if (normalizedTime < 1) {
						window.scrollTo(0, currentPostion + ((targetPosition - currentPostion) * Ease.easeInOut(normalizedTime)));
						requestAnimationFrame(loop);
					} else {
						window.scrollTo(0, targetPosition);
					}
				}
				requestAnimationFrame(loop);
			}
		});
	});
});

//pagetop
$(function () {
	var pagetop = $('.page_top_fx');
	var winHeight = $(window).height();

	$(window).resize(function () {
		var winHeight = $(window).height();
	});

	$(pagetop).hide();
	$(window).scroll(function () {
		if ($(this).scrollTop() > 300) {
			pagetop.fadeIn();
		} else {
			pagetop.fadeOut();
		}
	});
	pagetop.click(function () {
		$('body, html').animate({ scrollTop: 0 }, 500, "swing");
		return false;
	});
});

