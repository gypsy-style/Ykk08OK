{{-- resources/views/admin/products/show.blade.php --}}
@extends('admin.layouts.app')

@section('content')
    <h1>商品詳細</h1>
    <p><strong>商品コード:</strong> {{ $product->product_code }}</p>
    <p><strong>商品名:</strong> {{ $product->product_name }}</p>
    <p><strong>カテゴリー:</strong> {{ $product->category }}</p>
    <p><strong>容量:</strong> {{ $product->volume }}</p>
    <p><strong>価格:</strong> {{ $product->price }}</p>
    <p><strong>卸価格:</strong> {{ $product->wholesale_price }}</p>
    <p><strong>定価:</strong> {{ $product->retail_price }}</p>
    <p><strong>税率:</strong> {{ $product->tax_rate }}</p>
    <p><strong>JAN:</strong> {{ $product->jan }}</p>
    <p><strong>ステータス:</strong> {{ $product->status }}</p>
    @if($product->product_image)
        <p><strong>商品画像:</strong><br><img src="{{ asset('storage/app/public/' . $product->product_image) }}" width="200"></p>
    @endif
    <a href="{{ route('admin.products.index') }}" class="btn btn-secondary">一覧に戻る</a>
@endsection