<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    /**
     * ログ一覧を表示
     */
    public function index(Request $request)
    {
        $query = ActivityLog::with(['user', 'order.agency'])
            ->where('model_type', 'App\\Models\\Order')
            ->orderBy('created_at', 'desc');

        // アクションでフィルタリング
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // 日付範囲でフィルタリング
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // ステータス変更ログのみを表示する場合
        if ($request->get('status_only', false)) {
            $query->whereNotNull('old_status')->whereNotNull('new_status');
        }

        $logs = $query->paginate(20);

        // フィルタリング用のアクション一覧を取得
        $actions = ActivityLog::where('model_type', 'App\\Models\\Order')
            ->distinct()
            ->pluck('action')
            ->filter()
            ->values();

        return view('admin.logs.index', compact('logs', 'actions'));
    }
} 