@extends('agencies.layouts.app')

@section('title', '注文詳細')

@section('content')
<section class="lma-content">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>受注詳細</h2>
        </div>
    </div>
    <div class="lma-content_block order_detail">
        <dl class="lma-orderinfo_list">
            <dt>受注日</dt>
            <dd>{{ $order->created_at }}</dd>
            <dt>発注店舗</dt>
            <dd>{{ $order->merchant->name }}（{{$order->user->name ?? ''}}）</dd>
        </dl>
        <div class="lma-detail_wrap">
            <table class="lma-detail_tbl">
                <tbody>
                    @php
                    $totalQuantity = 0;
                    @endphp
                    @foreach ($order->details as $detail)
                    @php
                    $totalQuantity += $detail->quantity;
                    @endphp
                    <tr>
                        <th>{{ $detail->product->product_name }}</th>
                        <td>{{ $detail->quantity }} </td>
                        <td>{{ number_format($detail->price) }}円</td>
                    </tr>
                    @endforeach
                    <tr>
                        <th>送料</th>
                        <td></td>
                        <td>{{ $order->shipping_fee }}円</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <th>合計</th>
                        <td>{{$totalQuantity }}</td>
                        <td>{{ number_format(($order->total_price ?? 0) + ($order->shipping_fee ?? 0)) }}円</td>
                    </tr>
                </tfoot>
            </table>
            <div class="lma-detail_note">
						<p>備考：{{ $order->memo }}</p>
					</div>
        </div>
        <div class="lma-modifi_btns">
            <div class="lma-select_box">
                <!-- <label class="label">ステータス</label> -->
                @if ($order->status !=9 && $order->status >= 3)
                <span class="status-text">
                    @switch($order->status)
                    @case(3)
                    本部処理済み
                    @break
                    @case(4)
                    保留
                    @break
                    @case(5)
                    発送待ち
                    @break
                    @case(6)
                    発送済み
                    @break
                    @case(9)
                    キャンセル
                    @break
                    @default
                    不明なステータス
                    @endswitch
                </span>
                @else
                <select class="form-select status-dropdown" data-order-id="{{ $order->id }}">
                    <option value="1" {{ $order->status == 1 ? 'selected' : '' }}>代理店未処理</option>
                    <option value="2" {{ $order->status == 2 ? 'selected' : '' }}>代理店処理済み</option>
                </select>
                @endif
            </div>
            <!-- <div class="lma-btn_box btn_min">
                <button type="button">更新</button>
            </div> -->
        </div>
    </div>
    <div class="lma-btn_box">
        <a href="{{ route('agencies.orders.index') }}" class="bl">前に戻る</a>
    </div>
</section>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const statusDropdowns = document.querySelectorAll('.status-dropdown');

        statusDropdowns.forEach(dropdown => {
            dropdown.addEventListener('change', function() {
                const orderId = this.dataset.orderId;
                const status = this.value;
                fetch(`${BASE_URL}/agencies/orders/${orderId}/status`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            status: status
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('ステータスが更新されました');
                        } else {
                            alert('更新に失敗しました');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('エラーが発生しました');
                    });
            });
        });
    });
</script>
@endsection