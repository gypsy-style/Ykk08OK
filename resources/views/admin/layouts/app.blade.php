<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex,follow">
    <meta name="viewport" content="width=device-width, user-scalable=no">
    <meta name="format-detection" content="telephone=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '管理画面')</title>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    @vite([
    'resources/css/default.css',
    'resources/css/admin.css',
    'resources/js/common.js'])
    <script>
        const BASE_URL = "{{ config('app.url') }}";
    </script>
</head>

<body class="lma-point_body lma-orderlist_body">
    <div class="lma-container">
        <input id="burger_btn" class="burger_btn" type="checkbox">
        <aside class="lma-sidebar">
            <div class="lma-burger_open">
                <label class="open_btn" for="burger_btn"><span>&nbsp;</span></label>
            </div>
            <div class="lma-sinner">
                <div class="lma-logo_block">
                    <h1><a href="#"><span class="name img" style="display: none;"><img src="{{ asset('image/admin/logo_jiyugaoka.png') }}" alt=""></span><span class="text">本部専用管理画面</span></a></h1>
                </div>
                <div class="lma-user_block">
                    <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                    <p class="lma-btn_box btn_wh btn_min"><a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    ログアウト</a></p>
                </div>
                <div class="lma-navi_block">
                    <ul class="lma-navi_list">
                        <li class="dashboard {{ request()->routeIs('admin.dashboard') ? 'current' : '' }}">
                            <a href="{{ route('admin.dashboard') }}"><span class="text">ダッシュボード</span></a>
                        </li>
                        <li class="order {{ request()->routeIs('admin.orders.index') ? 'current' : '' }}">
                            <a href="{{ route('admin.orders.index') }}"><span class="text">受注管理</span></a>
                        </li>
                        <li class="store {{ request()->routeIs('admin.agencies.index') ? 'current' : '' }}">
                            <a href="{{ route('admin.agencies.index') }}"><span class="text">代理店管理</span></a>
                        </li>
                        <li class="store {{ request()->routeIs('admin.merchants.index') ? 'current' : '' }}">
                            <a href="{{ route('admin.merchants.index') }}"><span class="text">加盟店管理</span></a>
                        </li>
                        <li class="user {{ request()->routeIs('admin.users.index') ? 'current' : '' }}">
                            <a href="{{ route('admin.users.index') }}"><span class="text">ユーザー管理</span></a>
                        </li>
                        <li class="product {{ request()->routeIs('admin.products.index') ? 'current' : '' }}">
                            <a href="{{ route('admin.products.index') }}"><span class="text">商品管理</span></a>
                        </li>
                        <li class="log {{ request()->routeIs('admin.logs.index') ? 'current' : '' }}">
                            <a href="{{ route('admin.logs.index') }}"><span class="text">ログ一覧</span></a>
                        </li>
                    </ul>
                </div>
            </div>
        </aside>
        <main class="lma-main_contents">
            @yield('content')
        </main>
    </div>
</body>

</html>