@if ($orders->isEmpty())
<div class="lm-order_block lmf-white_block">
    <div class="lmf-order_recordnon_block">
        <p class="bold text_L">注文履歴はありません</p>
    </div>
</div>
@else
@foreach ($orders as $order)
@php
// ステータスラベルを取得
$statusLabels = [
1 => '代理店未処理',
2 => '代理店処理済み',
3 => '本部処理済み',
4 => '保留',
5 => '発送待ち',
6 => '発送済み',
9 => 'キャンセル'
];
$orderStatusLabel = $statusLabels[$order->status] ?? '不明なステータス';
@endphp

<div class="lm-order_block lmf-white_block">
    <h2 class="lmf-title_bar small gy center"><b class="label">注文番号：{{ $order->id }}</b></h2>
    <ul class="lmf_order_info_list">
        <li><em class="label">注文日時 </em>
            <span class="text">{{ $order->created_at->format('Y/m/d') }}</span>
        </li>
        <li><em class="label">注文合計金額</em>
            <span class="text">{{ number_format($order->total_price_included) }}円</span>
        </li>
        <li><em class="label">ステータス</em>
            <span class="text">{{ $orderStatusLabel }}</span>
        </li>
        <li><em class="label">備考</em>
            <span class="text">{{ $order->memo }}</span>
        </li>
        <li><em class="label">代理店名</em>
            <span class="text">{{ $order->agency->name }}</span>
        </li>
        <li><em class="label">発注者</em>
            <span class="text">{{ $order->user->name ?? ''}}</span>
        </li>
    </ul>
    <p class="lmf-btn_box">

        <a href="{{ route('order.detail', $order->id) }}">注文詳細</a>
    </p>
</div>
@endforeach
@endif