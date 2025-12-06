{{-- resources/views/admin/agency/create.blade.php --}}
@extends('admin.layouts.app')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>新規代理店登録</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        <form action="{{ route('admin.agencies.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <dl class="lma-form_box">
                <dt><label for="agency_code">代理店コード</label></dt>
                <dd><input type="text" class="form-control" id="agency_code" name="agency_code" value="{{ old('agency_code') }}" required></dd>

                <dt><label for="name">名前</label></dt>
                <dd><input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required></dd>

                <dt><label for="postal_code1">郵便番号1</label></dt>
                <dd><input type="text" class="form-control" id="postal_code1" name="postal_code1" value="{{ old('postal_code1') }}" required></dd>

                <dt><label for="postal_code2">郵便番号2</label></dt>
                <dd><input type="text" class="form-control" id="postal_code2" name="postal_code2" value="{{ old('postal_code2') }}" required></dd>

                <dt><label for="address">住所</label></dt>
                <dd><input type="text" class="form-control" id="address" name="address" value="{{ old('address') }}" required></dd>

                <dt><label for="phone">電話番号</label></dt>
                <dd><input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone') }}" required></dd>

                <dt><label for="email">メールアドレス</label></dt>
                <dd><input type="text" class="form-control" id="email" name="email" value="{{ old('email') }}" required></dd>

                <dt><label for="password">ログインパスワード</label></dt>
                <dd><input type="text" class="form-control" id="password" name="password" value="{{ old('password') }}" required></dd>


            </dl>

            <p class="lma-btn_box">
                <input type="hidden" class="form-control" id="user_id" name="user_id" value="{{ old('user_id') }}" required>
                <button type="submit" class="btn btn-primary">登録</button>
            </p>
        </form>
    </div>
</section>
    
@endsection