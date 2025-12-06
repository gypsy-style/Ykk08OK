@extends('admin.layouts.app')

@section('title', '管理画面 [アクティビティログ]')

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
default: return $action;
}
}
@endphp

@section('content')
<section class="lma-content flex">
    <div class="lma-main_head">
        <div class="lma-title_block">
            <h2>アクティビティログ</h2>
        </div>
    </div>

    <!-- フィルタリングフォーム -->
    <div class="lma-content_block log">
        <form method="GET" action="{{ route('admin.logs.index') }}" class="filter-form">
            <div class="lma-filter">
                <div class="lma-filter__item">
                    <label for="action">アクション:</label>
                    <select name="action" id="action">
                        <option value="">すべて</option>
                        @foreach($actions as $action)
                        <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>
                            {{ getActionText($action) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="lma-filter__item">
                    <label for="date_from">開始日:</label>
                    <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}">
                </div>
                <div class="lma-filter__item">
                    <label for="date_to">終了日:</label>
                    <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}">
                </div>
                <div class="lma-filter__item">
                    <label>
                        <input type="checkbox" name="status_only" value="1" {{ request('status_only') ? 'checked' : '' }}>
                        ステータス変更のみ
                    </label>
                </div>
                <div class="lma-filter__item">
                    <button type="submit" class="btn btn-primary">フィルター</button>
                    <a href="{{ route('admin.logs.index') }}" class="btn btn-secondary">リセット</a>
                </div>
            </div>
        </form>
    </div>

    <div class="lma-content_block  nobg">
        <ul class="lma-item_list log order">
            @foreach($logs as $log)
            <li>
                <div class="lma-log_box">
                    <div class="log_info">
                        <p class="data">{{ $log->created_at->format('Y/m/d H:i:s') }}</p>
                        <h3 class="company">{{ $log->order && $log->order->agency ? $log->order->agency->name : '-' }}</h3>
                    </div>
                    <div class="action_info">
                        <p class="action-type">{{ getActionText($log->action) }}</p>
                    </div>
                    <div class="log_content">
                        @if($log->old_status !== null && $log->new_status !== null)
                        <div class="status-change">
                            <span class="status-old">{{ getStatusText($log->old_status) }}</span>
                            <span class="status-arrow">→</span>
                            <span class="status-new">{{ getStatusText($log->new_status) }}</span>
                        </div>
                        @elseif($log->old_status !== null)
                        <div class="status-change">
                            <span class="status-old">{{ getStatusText($log->old_status) }}</span>
                        </div>
                        @elseif($log->new_status !== null)
                        <div class="status-change">
                            <span class="status-new">{{ getStatusText($log->new_status) }}</span>
                        </div>
                        @else
                        <div class="status-change">ステータス変更なし</div>
                        @endif

                        @if($log->description)
                        <div class="description">
                            <p>{{ $log->description }}</p>
                        </div>
                        @endif
                    </div>

                    <div class="lma-btn_box btn_min btn_gy">
                        @if($log->model_type && $log->model_id)
                        <a href="{{ route('admin.orders.show', $log->model_id) }}" class="btn btn-primary btn-sm">注文詳細</a>
                        @endif
                    </div>
                </div>
            </li>
            @endforeach
        </ul>
        <!-- ページネーション -->
        <div class="lma-pagination">
            @php
            $paginator = $logs->appends(request()->query());
            $current = $paginator->currentPage();
            $last = $paginator->lastPage();
            $start = max(1, $current - 2);
            $end = min($last, $current + 2);
            @endphp

            <a href="{{ $current > 1 ? $paginator->url(1) : '#' }}">&laquo;</a>
            <a href="{{ $paginator->previousPageUrl() ?? '#' }}">&lsaquo;</a>

            @for ($page = $start; $page <= $end; $page++)
                @if ($page == $current)
                    <span class="current">{{ $page }}</span>
                @else
                    <a href="{{ $paginator->url($page) }}" class="inactive">{{ $page }}</a>
                @endif
            @endfor

            <a href="{{ $paginator->nextPageUrl() ?? '#' }}">&rsaquo;</a>
            <a href="{{ $current < $last ? $paginator->url($last) : '#' }}">&raquo;</a>
        </div>
    </div>


</section>

<style>
    .filter-form {
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 5px;
    }

    .filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
    }

    .filter-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .filter-item label {
        font-weight: bold;
        margin-right: 5px;
    }

    .filter-item select,
    .filter-item input[type="date"] {
        padding: 5px;
        border: 1px solid #ddd;
        border-radius: 3px;
    }

    .action_info {
        margin: 5px 0;
    }

    .action-type {
        font-weight: bold;
        color: #007bff;
        margin: 0;
    }

    .user-info {
        font-size: 0.9em;
        color: #666;
        margin: 0;
    }

    .description {
        margin-top: 5px;
        font-size: 0.9em;
        color: #555;
    }
</style>
@endsection