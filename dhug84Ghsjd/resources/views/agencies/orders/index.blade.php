@extends('agencies.layouts.app')

@section('title', '注文一覧')

@section('content')
<section class="lma-content">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>受注一覧</h2>
        </div>
    </div>

    <div class="lma-content_block nobg">
        <ul class="lma-sort_list">
            <li>@if ($status == 1)
                <span>代理店未処理({{ $statusCounts[1] }})</span>
                @else
                <a href="{{ route('agencies.orders.index', ['status' => 1]) }}">代理店未処理({{ $statusCounts[1] }})</a>
                @endif
            </li>
            <li>@if ($status == 2)
                <span>代理店処理済み({{ $statusCounts[2] }})</span>
                @else
                <a href="{{ route('agencies.orders.index', ['status' => 2]) }}">代理店処理済み({{ $statusCounts[2] }})</a>
                @endif
            </li>
            <li>
                @if ($status == 3)
                <span>本部処理済み({{ $statusCounts[3] }})</span>
                @else
                <a href="{{ route('agencies.orders.index', ['status' => 3]) }}">本部処理済み({{ $statusCounts[3] }})</a>
                @endif
            </li>
            <li>
                @if ($status == 5)
                <span>発送待ち</span>
                @else
                <a href="{{ route('agencies.orders.index', ['status' => 5]) }}">発送待ち</a>
                @endif
            </li>
            <li>
                @if ($status == 6)
                <span>発送済み</span>
                @else
                <a href="{{ route('agencies.orders.index', ['status' => 6]) }}">発送済み</a>
                @endif
            </li>
            <li>
                @if ($status == 4)
                <span>保留({{ $statusCounts[4] }})</span>
                @else
                <a href="{{ route('agencies.orders.index', ['status' => 4]) }}">保留({{ $statusCounts[4] }})</a>
                @endif
            </li>
            <li>
                @if ($status == 9)
                <span>キャンセル</span>
                @else
                <a href="{{ route('agencies.orders.index', ['status' => 9]) }}">キャンセル</a>
                @endif
            </li>
        </ul>

        @if ($status == 1)
        <!-- 一括処理ボタン -->
        <div class="bulk-action lma-btn_box btn_wide">
            <button id="bulk-update-btn" class="btn btn-success">選択した注文を代理店処理済みにする</button>
        </div>
        @endif
        @if ($status == 1)
        <div class="bulk-action">
            <input type="checkbox" id="select-all">
            <label for="select-all">全選択</label>
        </div>
        @endif
        <ul class="lma-item_list order">
            @foreach ($orders as $order)
            <li>
                <div class="lma-order_box">
                    <div class="order_info">
                        @if ($status == 1)
                        <input type="checkbox" class="order-checkbox" value="{{ $order->id }}">
                        @endif
                        <p class="data">{{ $order->formatted_date }}</p>
                        <h3 class="company">{{ $order->agency->name ?? '未所属' }}</h3>
                        <h4 class="store">{{ $order->merchant->name ?? '未所属' }}</h4>
                        @if($order->last_status_change)
                        <p class="status-change-date">{{ $order->last_status_change['text'] }}</p>
                        @endif
                        <div class="price_box">
                            <p class="prices"><b class="price01">価格 {{ number_format($order->total_price) }}円</b></p>
                        </div>
                    </div>
                    <div class="modifi_btns">
                        @if ($order->status != 9 && $order->status >= 3)
                        <div class="status-text">
                            @switch($order->status)
                            @case(3)
                            <span>本部処理済み</span>
                            @break
                            @case(4)
                            <span>保留</span>
                            @break
                            @case(5)
                            <span>発送待ち</span>
                            @break
                            @case(6)
                            <span>発送済み</span>
                            @break
                            @case(9)
                            <span>キャンセル</span>
                            @break
                            @default
                            <span>不明なステータス</span>
                            @endswitch
                        </div>
                        @else
                        <div class="lma-select_box">
                            <select class="form-select status-dropdown" data-order-id="{{ $order->id }}">
                                <option value="1" {{ $order->status == 1 ? 'selected' : '' }}>代理店未処理</option>
                                <option value="2" {{ $order->status == 2 ? 'selected' : '' }}>代理店処理済み</option>
                                <option value="9" {{ $order->status == 9 ? 'selected' : '' }}>キャンセル</option>
                            </select>
                        </div>
                        @endif
                        <div class="lma-btn_box btn_min btn_gy">
                            <a href="{{ route('agencies.orders.show', $order->id) }}" class="btn btn-primary btn-sm">詳細</a>
                        </div>
                    </div>
                </div>
            </li>
            @endforeach
        </ul>
    </div>
</section>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const bulkUpdateUrl = "{{ route('agencies.orders.bulk-update') }}";
        const statusDropdowns = document.querySelectorAll('.status-dropdown');
        const bulkUpdateBtn = document.getElementById('bulk-update-btn');

        const selectAllCheckbox = document.getElementById('select-all');
        const orderCheckboxes = document.querySelectorAll('.order-checkbox');

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                orderCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
            });
        }


        statusDropdowns.forEach(dropdown => {
            dropdown.addEventListener('change', function() {
                const orderId = this.getAttribute('data-order-id');
                const newStatus = this.value;

                fetch(`${BASE_URL}/agencies/orders/${orderId}/status`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            status: newStatus
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('ステータスを更新しました。');
                            location.reload();
                        } else {
                            alert('ステータスの更新に失敗しました。');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            });
        });

        if (bulkUpdateBtn) {
            bulkUpdateBtn.addEventListener('click', function() {
                const selectedOrders = [];
                document.querySelectorAll('.order-checkbox:checked').forEach(checkbox => {
                    selectedOrders.push(checkbox.value);
                });

                if (selectedOrders.length === 0) {
                    alert('注文を選択してください。');
                    return;
                }

                if (!confirm('選択した注文を「代理店処理済み」に変更しますか？')) {
                    return;
                }

                fetch(bulkUpdateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            order_ids: selectedOrders,
                            status: 2
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('選択した注文のステータスを更新しました。');
                            location.reload();
                        } else {
                            alert('ステータスの更新に失敗しました。');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            });
        }
    });
</script>

@endsection