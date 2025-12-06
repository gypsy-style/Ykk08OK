{{-- resources/views/admin/products/show.blade.php --}}
@extends('admin.layouts.app')

@section('content')

<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>商品詳細</h2>
        </div>
    </div>
    <div class="lma-content_block store_edit">
    <form action="{{ route('admin.products.update', $product->id) }}" method="POST" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <dl class="lma-form_box">
        <dt><label for="product_code">商品コード</label></dt>
        <dd><p>{{ $product->product_code }}</p></dd>

        <dt><label for="product_name">商品名</label></dt>
        <dd><p>{{ $product->product_name }}</p></dd>

        <dt><label for="category">カテゴリー</label></dt>
        <dd>
        <p>{{ $product->category->name ?? '未設定' }}</p>
        </dd>

        <dt><label for="product_image">商品画像</label></dt>
        <dd>

            @if($product->product_image)
                <img src="{{ asset('storage/' . $product->product_image) }}" width="200" alt="Product Image">
            @endif
        </dd>

        <dt><label for="description">商品説明</label></dt>
        <dd>
        <p>{!! nl2br(e($product->description)) !!}</p>
        </dd>

        <dt><label for="volume">容量</label></dt>
        <dd>
        <p>{{ $product->volume }}</p>
        </dd>

        <dt><label for="price">価格</label></dt>
        <dd>
        <p>{{ $product->price }}</p>    
        </dd>

        <dt><label for="wholesale_price">卸価格</label></dt>
        <dd>
        <p>{{ $product->wholesale_price }}</p>    
        </dd>

        <dt><label for="retail_price">定価</label></dt>
        <dd>
        <p>{{ $product->retail_price }}</p>    
        </dd>

        <dt><label for="tax_rate">税率</label></dt>
        <dd>
        <p>{{ $product->tax_rate }}</p></dd>

        <dt><label for="jan">JAN</label></dt>
        <dd>
        <p>{{ $product->jan }}</p></dd>

        <dt><label for="status">ステータス</label></dt>
        <dd>
        <p>{{ $product->status }}</p>
        </dd>
    </dl>

    <p class="lma-btn_box">
        <a href="{{ route('admin.products.edit', $product->id) }}" class="btn btn-secondary">更新</a>
        <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">キャンセル</a>
    </p>
</form>
    </div>
</section>

@endsection