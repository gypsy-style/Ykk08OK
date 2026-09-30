@extends('admin.layouts.app')
@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>代理店情報の修正</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        @include('admin.agencies._errors')
        <form action="{{ route('admin.agencies.update', $agency->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <dl class="lma-form_box">
                <dt><label for="product_code">代理店コード</label></dt>
                <dd><input type="text" class="form-control" id="agency_code" name="agency_code" value="{{ old('agency_code', $agency->agency_code) }}" required></dd>

                <dt><label for="is_test">テスト代理店</label></dt>
                <dd>
                    <input type="hidden" name="is_test" value="0">
                    <label><input type="checkbox" id="is_test" name="is_test" value="1" {{ old('is_test', $agency->is_test) ? 'checked' : '' }}> テストデータとして扱う（配下の加盟店もすべてテスト扱いになります）</label>
                </dd>

                <dt><label for="product_code">名前</label></dt>
                <dd><input type="text" class="form-control" id="name" name="name" value="{{ old('name', $agency->name) }}" required></dd>

                <dt><label for="product_image">郵便番号1</label></dt>
                <dd><input type="text" class="form-control" id="postal_code1" name="postal_code1" maxlength="8" inputmode="numeric" placeholder="例：100" value="{{ old('postal_code1', $agency->postal_code1) }}" required></dd>

                <dt><label for="description">郵便番号2</label></dt>
                <dd><input type="text" class="form-control" id="postal_code2" name="postal_code2" maxlength="4" inputmode="numeric" placeholder="例：0001" value="{{ old('postal_code2', $agency->postal_code2) }}" required></dd>

                <dt><label for="volume">住所</label></dt>
                <dd><input type="text" class="form-control" id="address" name="address" value="{{ old('address', $agency->address) }}" required></dd>

                <dt><label for="price">電話番号</label></dt>
                <dd><input type="text" class="form-control" id="phone" name="phone" maxlength="15" inputmode="tel" value="{{ old('phone', $agency->phone) }}" required></dd>
                
                <dt><label for="wholesale_price">メールアドレス</label></dt>
                <dd><input type="email" class="form-control" id="email" name="email" value="{{ old('email', $agency->email) }}" required></dd>

                <dt><label for="password">新しいパスワード</label></dt>
                <dd><input type="text" name="password" id="password" class="form-control" minlength="8" autocomplete="new-password">
                    <div style="font-size:12px;color:#777;margin-top:4px;">変更する場合のみ入力（8文字以上）。空欄なら今のパスワードのままです。</div></dd>


            </dl>

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">更新</button>
            </p>
        </form>
    </div>
</section>

@endsection