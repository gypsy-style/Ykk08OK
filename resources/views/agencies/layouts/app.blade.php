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
                    <h1><a href="#"><span class="name img"><img src="{{ asset('image/admin/logo_jiyugaoka.png') }}" alt=""></span><span class="text">{{ auth()->user()->name.'様' ?? '代理店様' }}用管理画面</span></a></h1>
                </div>
                <div class="lma-user_block">
                <form id="logout-form" action="{{ route('agencies.logout') }}" method="POST" style="display: none;">
                        @csrf
                    </form>
                    <p class="lma-btn_box btn_wh btn_min"><a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    ログアウト</a></p>
                </div>
                <div class="lma-navi_block">
                    <ul class="lma-navi_list">
                        <li class="dashboard {{ request()->routeIs('agencies.dashboard') ? 'current' : '' }}">
                            <a href="{{ route('agencies.dashboard') }}"><span class="text">ダッシュボード</span></a>
                        </li>
                        <li class="order {{ request()->routeIs('agencies.orders.index') ? 'current' : '' }}">
                            <a class="nav-link" href="{{ route('agencies.orders.index') }}">受注管理</a>
                        </li>
                        <li class="order {{ request()->routeIs('agencies.merchants.index') ? 'current' : '' }}">
                            <a class="nav-link" href="{{ route('agencies.merchants.index') }}">加盟店管理</a>
                        </li>
                        <li class="user {{ request()->routeIs('agencies.users.index') ? 'current' : '' }}">
                            <a href="{{ route('agencies.users.index') }}"><span class="text">ユーザー管理</span></a>
                        </li>
                        
                        <li class="order {{ request()->routeIs('agencies.orders.list') ? 'current' : '' }}">
                            <a class="nav-link" href="{{ route('agencies.orders.list') }}">商品発注</a>
                        </li>

                    </ul>
                </div>
            </div>
        </aside>
        <main class="lma-main_contents">
            @yield('content')
        </main>
    </div>
    @stack('scripts') <!-- ページごとのスクリプト追加用 -->
</body>

</html>