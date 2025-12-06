@extends('admin.layouts.app')
@section('content')
@if ($errors->any())
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>加盟店情報の修正</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        <form action="{{ route('admin.merchants.update', $merchant->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <dl class="lma-form_box">
                <dt><label for="agency_id">代理店</label></dt>
                <dd>
                    <select class="form-control" id="agency_id" name="agency_id">
                        @foreach ($agencies as $agency)
                        <option value="{{ $agency->id }}" {{ $merchant->agency_id == $agency->id ? 'selected' : '' }}>
                            {{ $agency->name }}
                        </option>
                        @endforeach
                    </select>
                </dd>
                <dt><label for="product_code">サロン名</label></dt>
                <dd><input type="text" class="form-control" id="name" name="name" value="{{ old('name', $merchant->name) }}" required></dd>
                <dt><label for="product_code">サロンコード</label></dt>
                <dd><input type="text" class="form-control" id="merchant_code" name="merchant_code" value="{{ old('merchant_code', $merchant->merchant_code) }}" required></dd>
                <dt><label for="category_id">ステータス</label></dt>
                <dd>
                    <select class="form-control" id="status" name="status">
                        <option value="1" {{ $merchant->status == 1 ? 'selected' : '' }}>有効</option>
                        <option value="2" {{ $merchant->status == 2 ? 'selected' : '' }}>無効</option>
                    </select>
                </dd>

                <dt><label for="product_image">郵便番号1</label></dt>
                <dd><input type="text" class="form-control" id="postal_code1" name="postal_code1" value="{{ old('postal_code1', $merchant->postal_code1) }}" required></dd>

                <dt><label for="description">郵便番号2</label></dt>
                <dd><input type="text" class="form-control" id="postal_code2" name="postal_code2" value="{{ old('postal_code2', $merchant->postal_code2) }}" required></dd>

                <dt><label for="volume">住所</label></dt>
                <dd><input type="text" class="form-control" id="address" name="address" value="{{ old('address', $merchant->address) }}" required></dd>

                <dt><label for="price">電話番号</label></dt>
                <dd><input type="text" class="form-control" id="phone" name="phone" value="{{ old('phone', $merchant->phone) }}" required></dd>



            </dl>

            <p class="lma-btn_box">
                <input type="hidden" class="form-control" id="user_id" name="user_id" value="{{ old('user_id',$merchant->user_id) }}" required>
                <button type="submit" class="btn btn-primary">更新</button>
            </p>
        </form>
    </div>
</section>

@endsection