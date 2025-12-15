{{-- resources/views/admin/products/edit.blade.php --}}
@extends('admin.layouts.app')

@section('title', '商品編集')

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>設定</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
        <form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <dl class="lma-form_box">
                <dt><label for="product_code">商品コード</label></dt>
                <dd><input type="text" name="product_code" id="product_code" value="{{ old('product_code', $product->product_code) }}" required></dd>

                <dt><label for="product_name">商品名</label></dt>
                <dd><input type="text" name="product_name" id="product_name" value="{{ old('product_name', $product->product_name) }}" required></dd>

                <dt><label for="category">カテゴリー</label></dt>
                <dd>
                    <select name="category_id" id="category" required>
                        <option value="">選択してください</option>
                        @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id', $product->category_id ?? '') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                        @endforeach
                    </select>
                </dd>

                <dt><label for="product_image">商品画像</label></dt>
                <dd>
                    <input type="file" name="product_image" id="product_image">
                    @if($product->product_image)
                    <img src="{{ asset('storage/' . $product->product_image) }}" width="200" alt="Product Image">
                    @endif
                </dd>

                <dt><label for="description">商品説明</label></dt>
                <dd><textarea name="description" id="description">{{ old('description', $product->description) }}</textarea></dd>

                <dt><label for="volume">容量</label></dt>
                <dd><input type="text" name="volume" id="volume" value="{{ old('volume', $product->volume) }}"></dd>

                <dt><label for="price">サロン価格</label></dt>
                <dd><input type="number" name="price" id="price" value="{{ old('price', $product->price) }}" required></dd>

                <dt><label for="price_1">価格1（会員ランク1）</label></dt>
                <dd><input type="number" name="price_1" id="price_1" value="{{ old('price_1', $product->price_1) }}" placeholder="未入力ならサロン価格を使用"></dd>

                <dt><label for="price_2">価格2（会員ランク2）</label></dt>
                <dd><input type="number" name="price_2" id="price_2" value="{{ old('price_2', $product->price_2) }}" placeholder="未入力ならサロン価格を使用"></dd>

                <dt><label for="price_3">価格3（会員ランク3）</label></dt>
                <dd><input type="number" name="price_3" id="price_3" value="{{ old('price_3', $product->price_3) }}" placeholder="未入力ならサロン価格を使用"></dd>

                <dt><label for="wholesale_price">代理店価格</label></dt>
                <dd><input type="number" name="wholesale_price" id="wholesale_price" value="{{ old('wholesale_price', $product->wholesale_price) }}"></dd>

                <dt><label for="retail_price">定価</label></dt>
                <dd><input type="number" name="retail_price" id="retail_price" value="{{ old('retail_price', $product->retail_price) }}"></dd>

                <dt><label for="tax_rate">税率</label></dt>
                <dd><input type="number" name="tax_rate" id="tax_rate" value="{{ old('tax_rate', $product->tax_rate) }}" required></dd>

                <dt><label for="jan">JAN</label></dt>
                <dd><input type="text" name="jan" id="jan" value="{{ old('jan', $product->jan) }}"></dd>

                <dt><label for="jan">スタッフ商品コード</label></dt>
                <dd><input type="text" name="salon_product_code" id="salon_product_code" value="{{ old('jan', $product->salon_product_code) }}"></dd>

                <dt><label for="retail_price">スタッフセール価格</label></dt>
                <dd><input type="number" name="salon_price" id="salon_price" value="{{ old('jan', $product->salon_price) }}"></dd>
                <dt><label for="lot">商品ロット</label></dt>
                <dd>
                    <textarea name="lot" id="lot">{{ old('lot', $product->lot ?? '') }}</textarea>
                    <small>各ロットを改行で区切って入力してください。</small>
                </dd>

                <dt><label for="unit_quantity">商品入数</label></dt>
                <dd>
                    <input type="number" name="unit_quantity" id="unit_quantity" value="{{ old('unit_quantity', $product->unit_quantity) }}" min="1" required>
                    <small>1つの商品に含まれる個数を入力してください</small>
                </dd>

                <dt><label for="status">ステータス</label></dt>
                <dd>
                    <select name="status" id="status">
                        <option value="available" {{ old('status', $product->status) == 'available' ? 'selected' : '' }}>Available</option>
                        <option value="unavailable" {{ old('status', $product->status) == 'unavailable' ? 'selected' : '' }}>Unavailable</option>
                    </select>
                </dd>
                <dt><label for="agent_sale_flag">代理店販売停止</label></dt>
                <dd>
                <input type="checkbox" name="agent_sale_flag" id="agent_sale_flag" value="1"
                {{ old('agent_sale_flag', $product->agent_sale_flag ?? 0) == 1 ? 'checked' : '' }}>
                    <label for="agent_sale_flag">停止する</label>
                </dd>

                <dt><label>付属商品</label></dt>
                <dd>
                    <div class="accessories-container">
                        @php
                            $existingAccessories = $product->accessories->keyBy(function($item, $key) {
                                return $key + 1; // 1-based indexing for display
                            });
                        @endphp
                        @for($i = 1; $i <= 5; $i++)
                        @php
                            $existingAccessory = $existingAccessories->get($i);
                            $selectedProductId = $existingAccessory ? $existingAccessory->accessory_product_id : '';
                            $quantity = $existingAccessory ? $existingAccessory->quantity : '';
                        @endphp
                        <div class="accessory-row" style="margin-bottom: 10px; display: flex; gap: 10px; align-items: center;">
                            <label for="accessory_product_{{ $i }}" style="min-width: 80px;">{{ $i }}:</label>
                            <select name="accessories[{{ $i }}][product_id]" id="accessory_product_{{ $i }}" 
                                    class="form-control" style="flex: 1;">
                                <option value="">商品を選択してください</option>
                                @foreach($products as $availableProduct)
                                <option value="{{ $availableProduct->id }}" 
                                        {{ old('accessories.' . $i . '.product_id', $selectedProductId) == $availableProduct->id ? 'selected' : '' }}>
                                    【{{ $availableProduct->product_name }}（{{ $availableProduct->product_code }}）】
                                </option>
                                @endforeach
                            </select>
                            <input type="number" name="accessories[{{ $i }}][quantity]" id="accessory_quantity_{{ $i }}" 
                                   class="form-control" placeholder="個数" min="1" max="9999" style="width: 100px;"
                                   value="{{ old('accessories.' . $i . '.quantity', $quantity) }}">
                        </div>
                        @endfor
                    </div>
                    <small style="color: #666;">付属させる商品を選択し、個数を入力してください。個数は4桁まで入力可能です。</small>
                </dd>
            </dl>

            <p class="lma-btn_box">
                <button type="submit" class="btn btn-primary">更新</button>
                <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">キャンセル</a>
            </p>
        </form>
    </div>
</section>




@endsection