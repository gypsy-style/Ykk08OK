@extends('admin.layouts.app')

@section('title', '注文詳細')

@php
function getStatusText($status) {
switch($status) {
case 1: return '代理店未処理';
case 2: return '代理店処理済み';
case 3: return '本部処理済み';
case 4: return '保留';
case 5: return '発送待ち';
case 6: return '発送済み';
case 9: return 'キャンセル';
default: return '不明なステータス';
}
}

function getActionText($action) {
switch($action) {
case 'order_status_updated': return 'ステータス変更';
case 'order_created': return '注文作成';
case 'order_updated': return '注文更新';
case 'order_deleted': return '注文削除';
case 'order_cancelled': return '注文キャンセル';
default: return $action;
}
}
@endphp

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
			<dd>{{ optional($order->merchant)->name }}</dd>
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

		<div class="lma-log_wrap">
			<h3 class="lma-title_sub">操作ログ</h3>

			@if($logs->count() > 0)
			<ul class="lma-log_list">
				@foreach($logs as $log)
				<li>
					<div class="log-header">
						<span class="log-date">{{ $log->created_at->format('Y/m/d H:i:s') }}</span>
						<span class="log-action">{{ getActionText($log->action) }}</span>
						@if($log->user)
						<span class="log-user">操作者: {{ $log->user->name ?? $log->user->line_id ?? '不明' }}</span>
						@endif
					</div>
					<div class="log-content">
						@if($log->old_status !== null && $log->new_status !== null)
						<div class="status-change">
							<span class="status-old">{{ getStatusText($log->old_status) }}</span>
							<span class="status-arrow">→</span>
							<span class="status-new">{{ getStatusText($log->new_status) }}</span>
						</div>
						@elseif($log->old_status !== null)
						<div class="status-change">
							<span class="status-old">変更前: {{ getStatusText($log->old_status) }}</span>
						</div>
						@elseif($log->new_status !== null)
						<div class="status-change">
							<span class="status-new">変更後: {{ getStatusText($log->new_status) }}</span>
						</div>
						@endif
						@if($log->description)
						<div class="log-description">{{ $log->description }}</div>
						@endif
					</div>
				</li>
				@endforeach
			</ul>
			@else
			<div class="no-logs">
				<p>この注文の操作ログはありません。</p>
			</div>
			@endif
		</div>

		<div class="lma-modifi_btns">

			<div class="lma-select_box">
				<label class="label">ステータス</label>
				<select class="form-select status-dropdown" data-order-id="{{ $order->id }}">
					<option value="2" {{ $order->status == 2 ? 'selected' : '' }}>代理店処理済み</option>
					<option value="3" {{ $order->status == 3 ? 'selected' : '' }}>本部処理済み</option>
					<option value="5" {{ $order->status == 5 ? 'selected' : '' }}>発送待ち</option>
					<option value="6" {{ $order->status == 6 ? 'selected' : '' }}>発送済み</option>
					<option value="4" {{ $order->status == 4 ? 'selected' : '' }}>保留</option>
					<option value="9" {{ $order->status == 9 ? 'selected' : '' }}>キャンセル</option>
				</select>
			</div>
			<!-- <div class="lma-btn_box btn_min">
				<button type="button">更新</button>
			</div> -->
		</div>
	</div>
	<div class="lma-btn_box">
		<a href="{{ route('admin.orders.index') }}" class="bl">前に戻る</a>
	</div>
</section>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		const statusDropdowns = document.querySelectorAll('.status-dropdown');

		statusDropdowns.forEach(dropdown => {
			dropdown.addEventListener('change', function() {
				const orderId = this.dataset.orderId;
				const status = this.value;
				fetch(`${BASE_URL}/admin/orders/${orderId}/status`, {
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

<style>
	.log_list {
		margin: 20px 0;
		padding: 15px;
		background: #f8f9fa;
		border-radius: 5px;
	}

	.log_list h3 {
		margin: 0 0 15px 0;
		color: #333;
		font-size: 16px;
		font-weight: bold;
	}

	.log-items {
		max-height: 400px;
		overflow-y: auto;
	}

	.log-item {
		background: white;
		border: 1px solid #ddd;
		border-radius: 3px;
		margin-bottom: 10px;
		padding: 10px;
	}

	.log-header {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
		margin-bottom: 8px;
		font-size: 12px;
	}

	.log-date {
		color: #666;
		font-weight: bold;
	}

	.log-action {
		color: #007bff;
		font-weight: bold;
	}

	.log-user {
		color: #28a745;
	}

	.log-content {
		font-size: 13px;
	}

	.status-change {
		display: flex;
		align-items: center;
		gap: 5px;
		margin-bottom: 5px;
	}

	.status-old {
		color: #dc3545;
		font-weight: bold;
	}

	.status-arrow {
		color: #666;
	}

	.status-new {
		color: #28a745;
		font-weight: bold;
	}

	.log-description {
		color: #666;
		font-style: italic;
	}

	.no-logs {
		text-align: center;
		color: #666;
		font-style: italic;
		padding: 20px;
	}
</style>
@endsection