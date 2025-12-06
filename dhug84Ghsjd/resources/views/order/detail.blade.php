@extends('layouts.app')

@section('title', '注文詳細')

@section('content')
<div class="lmf-container">
    <div class="lmf-title_block tall">
        <h1 class="title">注文詳細</h1>
    </div>
    <main class="lmf-main_contents">
        <section class="lmf-content">
            <div class="lm-order_block lmf-white_block">
                <h2 class="lmf-title_bar small gy center">
                    <b class="label">注文番号：{{ $order->id }}</b>
                </h2>
                <ul class="lmf_order_info_list">
                    <li><em class="label">注文日時</em>
                        <span class="text">@if ($order && $order->created_at)
                            {{ $order->created_at->format('Y/m/d H:i') }}
                            @else
                            日付情報なし
                            @endif</span>
                    </li>
                    <li><em class="label">注文合計金額</em>
                        <span class="text">{{ number_format($order->total_price) }}円</span>
                    </li>
                    <li><em class="label">ステータス</em>
                        @php
                        // ステータスラベルを取得
                        $statusLabels = [
                        1 => '代理店未処理',
                        2 => '代理店処理済み',
                        3 => '本部処理済み',
                        4 => '保留',
                        5 => '発送待ち',
                        6 => '発送済み',
                        9 => 'キャンセル',
                        ];
                        $orderStatusLabel = $statusLabels[$order->status] ?? '不明なステータス';
                        @endphp
                        <span class="text">{{ $orderStatusLabel }}</span>
                    </li>
                    <li><em class="label">備考欄</em>
                        <span class="text">{{ $order->memo }}</span>
                    </li>
                    <li><em class="label">代理店名</em>
                        <span class="text">{{ $order->agency->name }}</span>
                    </li>
                    <li><em class="label">発注者</em>
                        <span class="text">{{ $order->user->name ?? ''}}</span>
                    </li>
                </ul>

                <h2 class="lmf-title_sub">ご注文内容</h2>
                <ul class="lmf-item_list vartical">
                    @foreach ($order->details as $detail)
                    <li data-cate=" cate8">
                        <div class="item_wrap">
                            <figure class="item_fig">
                                <img src="{{ asset('storage/' . $detail->product->product_image) }}" alt="no image" />
                            </figure>
                            <div class="item_info">
                                <span class="item_name">{{ $detail->product->set_sale_name ?: $detail->product->product_name }}</span>
                                <div class="item_cartin">
                                    <p class="item_unit-quantity">
                                        単価：{{ number_format($detail->price) }}円<br>数量：{{ $detail->quantity }}個
                                    </p>
                                    <b class="item_price">{{number_format($detail->price * $detail->quantity)}}円<small class="tax">(税込)</small></b>
                                </div>
                            </div>
                        </div>
                    </li>
                    @endforeach
                </ul>
                <div class="lmf-order_total">
                    <b class="label">合計</b><span class="quantity">[{{ $order->details->sum('quantity') }}点]</span><b class="item_price">{{number_format($order->total_price)}}円<small class="tax">(税込)</small></b>
                </div>
                @if($order->status == 1) {{-- 代理店未処理 --}}
                <p class="lmf-btn_box btn_small btn_dgy">
                    <button id="cancel-order-btn" data-order-id="{{ $order->id }}">キャンセル</button>
                </p>
                @endif
            </div>
            <p class="lmf-btn_box btn_small btn_gy"><a href="{{ route('order.history') }}">一覧に戻る</a></p>
        </section>
    </main>
</div>
<script>
document.getElementById('cancel-order-btn')?.addEventListener('click', function () {
        if (!confirm('本当にキャンセルしますか？')) return;

        const orderId = this.dataset.orderId;
        console.log(orderId);

        fetch("{{ route('order.cancel') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ order_id: orderId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('キャンセルしました');
                location.reload(); // または location.href = '{{ route('order.history') }}';
            } else {
                alert('キャンセル失敗: ' + data.error);
            }
        })
        .catch(error => {
            alert('エラーが発生しました: ' + error);
        });
    });
</script>

@endsection