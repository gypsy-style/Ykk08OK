@extends('agencies.layouts.app')

@section('title', '注文確認')

@section('content')
<section class="lma-content">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>注文確認</h2>
        </div>
    </div>
    <div class="lma-content_block nobg">
        <form action="{{route('agencies.orders.register')}}" method="post">
            @csrf
            <ul class="lma-item_list product">
                @foreach ($categories as $category)
                @foreach ($category->products as $product)
                @if (!empty($data['item_number_' . $product->id]))
                <li>
                    <div class="lma-product_box">
                        <div class="product_thumb">
                            <img src="{{ asset('storage/' . $product->product_image) }}" alt="">
                        </div>
                        <div class="product_info">
                            <p class="cate_box"><span class="cate">{{ $category->name }}</span></p>
                            <h3 class="name">{{ $product->product_name }}</h3>
                            <div class="price_box">
                                <p class="prices"><span class="volume">[{{ $product->volume }}]</span><b class="price01">価格 {{ $product->price }}円</b></p>
                                <p class="prices"><b class="price02">卸価格 {{ $product->wholesale_price }}円</b></p>
                            </div>
                            <p>数量: {{ $data['item_number_' . $product->id] }}</p>
                            <input type="hidden" name="item_number_{{ $product->id }}" value="{{ $data['item_number_' . $product->id] }}">
                        </div>
                    </div>
                </li>
                @endif
                @endforeach
                @endforeach
                <li>
                    <div class="lma-product_box lma-form_box">
                        <div class="product_info">
                            備考：
                            <textarea name="memo" id="memo"></textarea>
                        </div>
                    </div>
                </li>
            </ul>
            <p class="lma-btn_box btn_wide">
                <button type="submit">注文確定</button>
                <a href="{{ url()->previous() }}" class="btn">戻る</a>
            </p>
        </form>
    </div>
</section>
@endsection