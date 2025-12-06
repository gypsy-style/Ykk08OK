@extends('agencies.layouts.login')

@section('title', 'LME ORDER 管理画面 [ログイン]')

@section('content')
<div class="lma-content_block login">
    <div class="lma-login-form">
        <div class="lma-title_block">
            <h2 class="min center">ログインフォーム</h2>
        </div>
        <form method="post" action="{{ route('agencies.login') }}">
            @csrf
            <dl class="lma-form_box">
                <dt>メールアドレス</dt>
                <dd><input type="text" name="email" id="email" required></dd>
                <dt>パスワード</dt>
                <dd><input type="password" name="password" id="login_password" required></dd>
            </dl>
            <p class="lma-btn_box">
                <input type="submit" value="ログイン" class="submit">
            </p>
        </form>
    </div>
</div>
@endsection