<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex,follow">
    <meta name="viewport" content="width=device-width,user-scalable=no">
    <meta name="format-detection" content="telephone=no" />

    

    <title>@yield('title', '管理画面')</title>

    <script type="text/javascript" src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    @vite([
        'resources/css/default.css',
        'resources/css/admin.css',
        'resources/js/common.js'])
    <script type="text/javascript" src="{{ asset('js/common.js') }}"></script>
</head>
<body class="lma-point_body lma-login_body">
    <div class="lma-container login">
        <main class="lma-main_contents onecolumn">
            @yield('content')
        </main>
    </div>
</body>
</html>