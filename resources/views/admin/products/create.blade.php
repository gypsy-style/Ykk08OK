{{-- resources/views/admin/products/create.blade.php --}}
@extends('admin.layouts.app')

@section('content')

<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>新規商品登録</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        <form action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <dl class="lma-form_box">
                <dt><label for="product_code">商品コード</label></dt>
                <dd><input type="text" name="product_code" id="product_code" class="form-control" required></dd>

                <dt><label for="product_name">商品名</label></dt>
                <dd><input type="text" name="product_name" id="product_name" class="form-control" required></dd>

                <dt><label for="set_sale_name">セット販売用商品名</label></dt>
                <dd><input type="text" name="set_sale_name" id="set_sale_name" class="form-control" placeholder="セット表示時に使う名称（任意）"></dd>

                <dt><label for="category_id">カテゴリー</label></dt>
                <dd>
                    <select name="category_id" id="category_id" class="form-control" required>
                        <option value="">選択してください</option>
                        @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </dd>

                <dt><label for="unit_quantity">商品入数</label></dt>
                <dd>
                    <input type="number" name="unit_quantity" id="unit_quantity" class="form-control" value="1" min="1" required>
                    <small>1つの商品に含まれる個数を入力してください</small>
                </dd>

                <dt><label for="product_image">商品画像</label></dt>
                <dd><input type="file" name="product_image" id="product_image" class="form-control"></dd>

                <dt><label for="description">商品説明</label></dt>
                <dd><textarea name="description" id="description" class="form-control"></textarea></dd>

                <dt><label for="volume">容量</label></dt>
                <dd><input type="text" name="volume" id="volume" class="form-control"></dd>

                <dt><label for="price">サロン価格</label></dt>
                <dd><input type="number" name="price" id="price" class="form-control" required></dd>

                <dt><label for="price_1">価格1（会員ランク1）</label></dt>
                <dd><input type="number" name="price_1" id="price_1" class="form-control" placeholder="未入力ならサロン価格を使用"></dd>

                <dt><label for="price_2">価格2（会員ランク2）</label></dt>
                <dd><input type="number" name="price_2" id="price_2" class="form-control" placeholder="未入力ならサロン価格を使用"></dd>

                <dt><label for="price_3">価格3（会員ランク3）</label></dt>
                <dd><input type="number" name="price_3" id="price_3" class="form-control" placeholder="未入力ならサロン価格を使用"></dd>

                <dt><label for="wholesale_price">代理店価格</label></dt>
                <dd><input type="number" name="wholesale_price" id="wholesale_price" class="form-control"></dd>

                <dt><label for="retail_price">定価</label></dt>
                <dd><input type="number" name="retail_price" id="retail_price" class="form-control"></dd>

                <dt><label for="tax_rate">税率</label></dt>
                <dd><input type="number" name="tax_rate" id="tax_rate" class="form-control" required></dd>

                <dt><label for="jan">JAN</label></dt>
                <dd><input type="text" name="jan" id="jan" class="form-control"></dd>

                <dt><label for="jan">スタッフ商品コード</label></dt>
                <dd><input type="text" name="salon_product_code" id="salon_product_code" class="form-control"></dd>

                <dt><label for="retail_price">スタッフセール価格</label></dt>
                <dd><input type="number" name="salon_price" id="salon_price" class="form-control"></dd>

                <dt><label for="lot">商品ロット</label></dt>
                <dd>
                    <textarea name="lot" id="lot"></textarea>
                    <small>各ロットを改行で区切って入力してください。</small>
                </dd>

                <dt><label for="status">ステータス</label></dt>
                <dd>
                    <select name="status" id="status" class="form-control">
                        <option value="available">Available</option>
                        <option value="unavailable">Unavailable</option>
                    </select>
                </dd>
                <dt><label for="agent_sale_flag">代理店販売停止</label></dt>
                <dd>
                    <input type="checkbox" name="agent_sale_flag" id="agent_sale_flag" value="1">
                    <label for="agent_sale_flag">停止する</label>
                </dd>

                <dt><label for="single_sale_prohibited">単品販売不可</label></dt>
                <dd>
                    <input type="checkbox" name="single_sale_prohibited" id="single_sale_prohibited" value="1">
                    <label for="single_sale_prohibited">単品としての販売を不可にする</label>
                </dd>

                <dt><label>付属商品</label></dt>
                <dd>
                    <div class="accessories-container">
                        @for($i = 1; $i <= 5; $i++)
                        <div class="accessory-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                            <label for="accessory_product_{{ $i }}" style="min-width: 80px;">{{ $i }}:</label>
                            <select name="accessories[{{ $i }}][product_id]" id="accessory_product_{{ $i }}" 
                                    class="form-control" style="flex: 1;">
                                <option value="">商品を選択してください</option>
                                @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->product_name }}（{{ $product->product_code }}）</option>
                                @endforeach
                            </select>
                            <input type="number" name="accessories[{{ $i }}][quantity]" id="accessory_quantity_{{ $i }}" 
                                   class="form-control" placeholder="個数" min="1" max="9999" style="width: 100px;">
                        </div>
                        @endfor
                    </div>
                    <small style="color: #666;">付属させる商品を選択し、個数を入力してください。個数は4桁まで入力可能です。</small>
                </dd>
            </dl>

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">登録</button>
            </p>
        </form>
    </div>
</section>
@endsection