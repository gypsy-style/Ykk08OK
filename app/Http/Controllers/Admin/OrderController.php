<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    /**
     * 注文一覧を表示
     */
    public function index(Request $request)
    {
        // GETパラメータからstatusを取得（デフォルトは1）
        $status = $request->get('status', 2);
        $orders = Order::with(['merchant', 'details.product', 'agency', 'statusChangeLogs'])
            ->where('status', $status)
            ->orderBy('created_at', 'desc') // 追加: created_at を降順でソート
            ->get();
            // dd($orders);

        //代理店処理済みの受注
        $agenciesProcessed = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price')
            ->where('status', 2)
            ->first();

        // 本部処理済みの受注
        $headquartersProcessed = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price')
            ->where('status', 3)
            ->first();

            // 各statusの件数を取得
            $statusCounts = DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereIn('status', [2, 3, 4, 5, 6, 9]) // 対象とするステータス
            ->groupBy('status')
            ->pluck('count', 'status') // 結果を 'status' => 'count' の形式で取得
            ->toArray();
            
        
        // 全ステータスを初期化し、結果をマージして不足分を補完
        $statusCounts = array_replace([ 2 => 0, 3 => 0, 4 => 0,5 => 0,6 => 0,9 => 0], $statusCounts);



            // dd($headquartersProcessed);
        return view('admin.orders.index', compact('status','orders','agenciesProcessed','headquartersProcessed','statusCounts'));
    }

    /**
     * 注文詳細を表示
     */
    public function show(Order $order)
    {
        // 注文詳細と関連情報を取得
        $order->load('details.product', 'merchant', 'agency');

        // この注文に関連するログを取得
        $logs = \App\Models\ActivityLog::with(['user'])
            ->where('model_type', 'App\\Models\\Order')
            ->where('model_id', $order->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // コピー用テキスト生成
        $merchant = $order->merchant;
        $lines = [];
        $lines[] = $merchant->name ?? '';
        if ($merchant && $merchant->postal_code1 && $merchant->postal_code2) {
            $lines[] = $merchant->postal_code1 . '-' . $merchant->postal_code2;
        }
        $lines[] = $merchant->address ?? '';
        $lines[] = $merchant->phone ?? '';
        $lines[] = '';
        $totalQty = 0;
        foreach ($order->details as $detail) {
            $totalQty += $detail->quantity;
            $lines[] = $detail->product->product_name . '×' . $detail->quantity . '個' . number_format($detail->price) . '円';
        }
        $taxAmount = (int) round(($order->total_price ?? 0) * 1.1) - ($order->total_price ?? 0);
        $lines[] = '送料' . ($order->shipping_fee ?? 0) . '円';
        $lines[] = '消費税' . number_format($taxAmount) . '円';
        $lines[] = '合計' . $totalQty . '個' . number_format((int) round(($order->total_price ?? 0) * 1.1) + ($order->shipping_fee ?? 0)) . '円';
        $copyText = implode("\n", $lines);

        return view('admin.orders.show', compact('order', 'logs', 'copyText'));
    }

    public function edit(Order $order)
    {
        $categories = Order::all();
        $order->load('order');
        return view('admin.products.edit', compact('product', 'categories'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        // バリデーション
        $validated = $request->validate([
            'status' => 'required|integer|in:2,3,4,5,6,9'
        ]);

        // 変更前の値を保存
        $oldStatus = $order->status;

        // ステータス更新
        $order->status = $validated['status'];
        $order->save();

        // 変更後の値を取得
        $newStatus = $order->status;

        // ログを記録
        $this->activityLogService->logOrderStatusUpdated($order, $oldStatus, $newStatus);

        // 成功レスポンス
        return response()->json(['success' => true]);
    }

    public function updateShippingFee(Request $request, Order $order)
    {
        $validated = $request->validate([
            'shipping_fee' => 'required|integer|min:0'
        ]);

        if ($order->status !== 2) {
            return response()->json(['success' => false, 'message' => 'ステータスが2の注文のみ送料を変更できます'], 403);
        }

        $order->shipping_fee = $validated['shipping_fee'];
        $order->save();

        return response()->json(['success' => true]);
    }

    public function bulkUpdate(Request $request)
    {
        $orderIds = $request->order_ids;
        $newStatus = $request->status;

        // 一括更新前の注文データを取得
        $orders = Order::whereIn('id', $orderIds)->get();

        // 一括更新を実行
        Order::whereIn('id', $orderIds)->update(['status' => $newStatus]);

        // 各注文のログを記録
        foreach ($orders as $order) {
            $oldStatus = $order->status;
            $this->activityLogService->logOrderStatusUpdated($order, $oldStatus, $newStatus);
        }

        return response()->json(['success' => true]);
    }
}
