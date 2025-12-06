/*--------------------------------------------------------------------------*
 *
 * common script
 *
 *--------------------------------------------------------------------------*/

//スムーズスクロール:non jquery
var Ease = {
	easeInOut: t => t<.5 ? 4*t*t*t : (t-1)*(2*t-2)*(2*t-2)+1
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
$(function() {
	var pagetop = $('.page_top_fx');
	var winHeight = $(window).height();

	$(window).resize(function(){
		var winHeight = $(window).height();
	});
	
	$(pagetop).hide();
	$(window).scroll(function () {
		if ($(this).scrollTop() > 500) {
			pagetop.fadeIn();
		} else {
			pagetop.fadeOut();
		}
	});
	pagetop.click(function () {
		$('body, html').animate({ scrollTop: 0 }, 600, "swing");
		return false;
	});
});

//リンククリックしたらバーガー閉じる
$(function(){
	$('.burger_content a').click(function(){
		$('#burger_btn').prop("checked",false);
	});
});
