<!doctype html>

<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width,user-scalable=no">
	<meta name="format-detection" content="telephone=no" />
	<!-- 条件分岐でCSRFトークンを挿入 -->
	@if (Route::is('order.history') 
	OR Route::is('order.list')
	OR Request::is('merchants/create') 
	OR Request::is('merchants/information')
	OR Request::is('merchants/member_list')
	OR Request::is('merchants/add_member')
	)
	<meta name="csrf-token" content="{{ csrf_token() }}">
	@endif

	<title>@yield('title', 'LME ORDER')</title>


	<script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>

	@vite([
	'resources/css/default.css',
	'resources/css/front.css',
	'resources/css/splide.min.css',
	'resources/js/app.js',
	'resources/js/line_form_order.js',
	'resources/js/splide.min.js'])
	<script type="module">
		import Splide from '{{ Vite::asset("resources/js/splide.min.js") }}';
		window.Splide = Splide;
	</script>

	<!-- ■ splide ■ -->


</head>

<body class="lmf-mypage_body cust">

	<!-- メインコンテンツ -->
	@yield('content')
	@stack('scripts') <!-- ページごとのスクリプト追加用 -->
</body>

</html>