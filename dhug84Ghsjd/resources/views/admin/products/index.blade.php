@extends('admin.layouts.app')

@section('title', '管理画面 [商品]')

@section('content')
<section class="lma-content">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>商品管理</h2>
        </div>
    </div>
    <div>
        <p class="lma-btn_box"><a href="{{ route('admin.products.create') }}">商品新規作成</a></p>
    </div>
    <div class="lma-content_block nobg">
        <ul class="lma-item_list product">
            @foreach($products as $product)
            <li>
                <div class="lma-product_box">
                    <div class="product_thumb">
                        <img src="{{ asset('storage/' . $product->product_image) }}">
                    </div>
                    <div class="product_info">
                        <div class="modifi_btns">
                            <div class="lma-select_box">
                                <select name="status" class="status-select" data-product-id="{{ $product->id }}">
                                    <option value="available" {{ $product->status === 'available' ? 'selected' : '' }}>販売中</option>
                                    <option value="unavailable" {{ $product->status === 'unavailable' ? 'selected' : '' }}>完売</option>
                                </select>
                            </div>
                            <div class="lma-btn_box btn_min">
                                <a href="{{ route('admin.products.edit', $product->id) }}">編集</a>
                            </div>
                            <div class="lma-btn_box btn_min btn_gy">
                                <a href="{{ route('admin.products.show', $product->id) }}">詳細</a>
                            </div>
                        </div>
                        <p class="cate_box"><span class="cate">{{ $product->category->name ?? 'カテゴリー未設定' }}</span></p>
                        <h3 class="name">{{ $product->set_sale_name ?: $product->product_name }}
                            @php
                                $badges = [];
                                if ($product->agent_sale_flag == 1) $badges[] = '代理店注文停止中';
                                if (!empty($product->single_sale_prohibited) && (int)$product->single_sale_prohibited === 1) $badges[] = '単品販売中止中';
                            @endphp
                            @if(!empty($badges))
                                <span class="color__pk bold">（{{ implode('、', $badges) }}）</span>
                            @endif
                        </h3>
                        <div class="price_box">
                            <p class="prices"><span class="volume">{{$product->volume !='' ? '['.$product->volume.']' : '' }}</span><b class="price01">価格 {{ $product->price }}円</b></p>
                            <p class="prices"><b class="price02">卸価格 {{ $product->wholesale_price }}円</b></p>
                        </div>
                    </div>
                </div>
            </li>
            @endforeach
        </ul>

        <div class="lma-pagination">
            @php
                $current = $products->currentPage();
                $last = $products->lastPage();
                $start = max(1, $current - 2);
                $end = min($last, $current + 2);
            @endphp
            <a href="{{ $current > 1 ? $products->url(1) : '#' }}">&laquo;</a>
            <a href="{{ $products->previousPageUrl() ?? '#' }}">&lsaquo;</a>
            @for ($page = $start; $page <= $end; $page++)
                @if ($page == $current)
                    <span class="current">{{ $page }}</span>
                @else
                    <a href="{{ $products->url($page) }}" class="inactive">{{ $page }}</a>
                @endif
            @endfor
            <a href="{{ $products->nextPageUrl() ?? '#' }}">&rsaquo;</a>
            <a href="{{ $current < $last ? $products->url($last) : '#' }}">&raquo;</a>
        </div>
    </div>
</section>
@endsection
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selects = document.querySelectorAll('.status-select');

    selects.forEach(select => {
        select.addEventListener('change', function () {
            const productId = this.dataset.productId;
            const newStatus = this.value;

            fetch("{{ route('admin.products.updateStatus') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    id: productId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
            })
            .catch(error => {
                alert('エラーが発生しました');
                console.error(error);
            });
        });
    });
});
</script>