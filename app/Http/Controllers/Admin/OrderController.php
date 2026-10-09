<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\InvoiceService;
use App\Services\TestDataFilter;
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
        // GETパラメータからstatusを取得（デフォルトは本部未処理）
        $status = (int) $request->get('status', Order::STATUS_HQ_PENDING);
        // 未指定ならテストを除外する
        $excludeTest = $request->query('exclude_test', '1') !== '0';

        $ordersQuery = Order::with(['merchant.agency', 'details.product', 'agency', 'statusChangeLogs'])
            ->whereIn('status', Order::statusesForTab($status))
            ->orderBy('created_at', 'desc');
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($ordersQuery);
        }
        $orders = $ordersQuery->get();

        // 本部未処理の受注
        $hqPendingQuery = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price')
            ->whereIn('status', Order::statusesForTab(Order::STATUS_HQ_PENDING));
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($hqPendingQuery);
        }
        $hqPending = $hqPendingQuery->first();

        // 発送待ちの受注
        $awaitingShipmentQuery = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price')
            ->whereIn('status', Order::statusesForTab(Order::STATUS_AWAITING_SHIPMENT));
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($awaitingShipmentQuery);
        }
        $awaitingShipment = $awaitingShipmentQuery->first();

        $statusCounts = Order::adminStatusCounts($excludeTest);

        return view('admin.orders.index', compact('status','orders','hqPending','awaitingShipment','statusCounts','excludeTest'));
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
        $lines[] = '消費税（10%）' . number_format($taxAmount) . '円';
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
        $newStatus = (int) $validated['status'];

        // ステータス更新
        $order->status = $newStatus;

        // 発送済みになった瞬間だけ発送日を記録し、発送済みから外れたら取り消す。
        // すでに発送済みのまま再保存された場合は既存の発送日を動かさない
        if ($newStatus === InvoiceService::SHIPPED_STATUS) {
            if ((int) $oldStatus !== InvoiceService::SHIPPED_STATUS) {
                $order->shipped_at = now();
            }
        } else {
            $order->shipped_at = null;
        }

        $order->save();

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

        if ((int) $order->status !== Order::STATUS_HQ_PENDING) {
            return response()->json(['success' => false, 'message' => '本部未処理の注文のみ送料を変更できます'], 403);
        }

        $order->shipping_fee = $validated['shipping_fee'];
        $order->save();

        return response()->json(['success' => true]);
    }

    /**
     * 本部未処理の注文を送料込みで確定し、発送待ちにする
     */
    public function confirm(Request $request, Order $order)
    {
        $validated = $request->validate([
            'shipping_fee' => 'required|integer|min:0'
        ]);

        if ((int) $order->status !== Order::STATUS_HQ_PENDING) {
            return response()->json(['success' => false, 'message' => '本部未処理の注文のみ確定できます'], 403);
        }

        $oldStatus = $order->status;
        $order->shipping_fee = $validated['shipping_fee'];
        $order->status = Order::STATUS_AWAITING_SHIPMENT;
        $order->save();

        $this->activityLogService->logOrderStatusUpdated($order, $oldStatus, Order::STATUS_AWAITING_SHIPMENT);

        return response()->json(['success' => true]);
    }

    public function bulkUpdate(Request $request)
    {
        // バリデーション
        $validated = $request->validate([
            'status' => 'required|integer|in:2,3,4,5,6,9'
        ]);

        $orderIds = $request->order_ids;
        $newStatus = (int) $validated['status'];

        // 一括更新前の注文データを取得
        $orders = Order::whereIn('id', $orderIds)->get();

        // 発送済みへの変更なら発送日を記録し、発送済みから外れる場合は取り消す。
        // すでに発送済みだったものは発送日を動かさない
        if ($newStatus === InvoiceService::SHIPPED_STATUS) {
            Order::whereIn('id', $orderIds)
                ->where('status', '!=', InvoiceService::SHIPPED_STATUS)
                ->update(['shipped_at' => now()]);
        } else {
            Order::whereIn('id', $orderIds)
                ->where('status', InvoiceService::SHIPPED_STATUS)
                ->update(['shipped_at' => null]);
        }

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
