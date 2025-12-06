{{-- resources/views/agencies/agency/create.blade.php --}}
@extends('agencies.layouts.app')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>新しい加盟店を登録</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        <form action="{{ route('agencies.merchants.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <dl class="lma-form_box">
                <dt><label for="name">サロン名</label></dt>
                <dd><input type="text" class="form-control" id="name" name="name" value="{{ old('name') }}" required></dd>
                <dt><label for="product_code">サロンコード</label></dt>
                <dd><input type="text" class="form-control" id="merchant_code" name="merchant_code" value="{{ old('merchant_code') }}" required></dd>

                <dt><label for="category_id">ステータス</label></dt>
                <dd>
                    <select class="form-control" id="status" name="status">
                        <option value="1" {{ old('status') == 1 ? 'selected' : '' }}>承認</option>
                        <option value="2" {{ old('status') == 2 ? 'selected' : '' }}>非承認</option>
                    </select>
                </dd>

                <dt><label for="product_image">郵便番号1</label></dt>
                <dd><input type="text" class="form-control" id="postal_code1" name="postal_code1" value="{{ old('postal_code1') }}" required></dd>

                <dt><label for="description">郵便番号2</label></dt>
                <dd><input type="text" class="form-control" id="postal_code2" name="postal_code2" value="{{ old('postal_code2') }}" required></dd>

                <dt><label for="volume">住所</label></dt>
                <dd><input type="text" class="form-control" id="address" name="address" value="{{ old('address') }}" required></dd>

                <dt><label for="price">電話番号</label></dt>
                <dd><input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone') }}" required></dd>

            
            </dl>

            <p class="lma-btn_box">
            <input type="hidden" class="form-control" id="user_id" name="user_id" value="{{ old('user_id') }}" required>
                <button type="submit" class="btn btn-primary">登録</button>
            </p>
        </form>
    </div>
</section>
@endsection