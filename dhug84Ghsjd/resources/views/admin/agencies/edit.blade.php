@extends('admin.layouts.app')
@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>代理店情報の修正</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        <form action="{{ route('admin.agencies.update', $agency->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <dl class="lma-form_box">
                <dt><label for="product_code">代理店コード</label></dt>
                <dd><input type="text" class="form-control" id="agency_code" name="agency_code" value="{{ old('agency_code', $agency->agency_code) }}" required></dd>

                <dt><label for="product_code">名前</label></dt>
                <dd><input type="text" class="form-control" id="name" name="name" value="{{ old('name', $agency->name) }}" required></dd>

                <dt><label for="product_image">郵便番号1</label></dt>
                <dd><input type="text" class="form-control" id="postal_code1" name="postal_code1" value="{{ old('postal_code1', $agency->postal_code1) }}" required></dd>

                <dt><label for="description">郵便番号2</label></dt>
                <dd><input type="text" class="form-control" id="postal_code2" name="postal_code2" value="{{ old('postal_code2', $agency->postal_code2) }}" required></dd>

                <dt><label for="volume">住所</label></dt>
                <dd><input type="text" class="form-control" id="address" name="address" value="{{ old('address', $agency->address) }}" required></dd>

                <dt><label for="price">電話番号</label></dt>
                <dd><input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $agency->phone) }}" required></dd>
                
                <dt><label for="wholesale_price">メールアドレス</label></dt>
                <dd><input type="text" class="form-control" id="email" name="email" value="{{ old('email', $agency->email) }}" required></dd>

                <dt><label for="password">新しいパスワード</label></dt>
                <dd><input type="text" name="password" id="password" class="form-control" required></dd>


            </dl>

            <p class="lma-btn_box">
                <input type="hidden" class="form-control" id="user_id" name="user_id" value="{{ old('user_id') }}" required>
                <button type="submit" class="btn btn-primary">更新</button>
            </p>
        </form>
    </div>
</section>

@endsection