<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Category;
use App\Models\User;
use App\Models\Product;
use App\Services\ActivityLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $status = $request->get('status', 1);
        // ログイン中のagency_idを取得
        $agencyId = auth('agencies')->user()->id;

        // merchant経由でagency_idに紐づく注文を取得
        $orders = Order::with(['merchant' => function ($query) {
            $query->select('id', 'name', 'agency_id');
        }, 'merchant.agency', 'details.product', 'statusChangeLogs'])
            ->whereHas('merchant', function ($query) use ($agencyId) {
                $query->where('agency_id', $agencyId);
            })
            ->where('status', $status)
            ->whereIn('status', [1, 2, 3, 4, 5, 6, 9])
            ->orderBy('created_at', 'desc')
            ->get();

        $statusCounts = DB::table('orders')
            ->join('merchants', 'orders.merchant_id', '=', 'merchants.id')
            ->where('merchants.agency_id', $agencyId)
            ->whereIn('orders.status', [1, 2, 3, 4, 5, 6, 9])
            ->select('orders.status', DB::raw('COUNT(*) as count'))
            ->groupBy('orders.status')
            ->pluck('count', 'orders.status')
            ->toArray();

        // 全ステータスを初期化し、結果をマージして不足分を補完
        $statusCounts = array_replace([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 9 => 0], $statusCounts);

        return view('agencies.orders.index', compact('status', 'orders', 'statusCounts'));
    }

    /**
     * 発注ページ
     */
    public function list()
    {
        $categories = Category::with(['products' => function ($query) {
            $query->where('agent_sale_flag', '!=', 1)
                ->where('status', 'available');
        }])->get();

        return view('agencies.orders.list', compact('categories'));
    }

    public function confirmation(Request $request)
    {
        $data = $request->all();
        $categories = Category::with('products')->get();

        return view('agencies.orders.confirmation', compact('data', 'categories'));
    }

    public function register(Request $request)
    {
        $merchantId = 0;
        $status = 2;
        $agencyId = auth()->user()->id;
        $data = $request->all();
        $memo = $request->input('memo', null);
        $totalPrice = 0;
        $orderDetails = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'item_number_') && $value > 0) {
                $productId = str_replace('item_number_', '', $key);
                $product = Product::find($productId);
                if ($product) {
                    $quantity = (int) $value;
                    $price = $product->wholesale_price * $quantity;

                    $totalPrice += $price;

                    $orderDetails[] = [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                        'price' => $price,
                    ];
                }
            }
        }

        if (empty($orderDetails)) {
            return redirect()->back()->with('error', '少なくとも1つの商品を選択してください。');
        }

        // オーダーを作成
        $order = Order::create([
            'status' => $status,
            'merchant_id' => $merchantId,
            'agency_id' => $agencyId,
            'total_price' => $totalPrice,
            'memo' => $memo,
        ]);

        // オーダー詳細を挿入
        foreach ($orderDetails as $detail) {
            $detail['order_id'] = $order->id;
            OrderDetail::create($detail);
        }

        // ログを記録
        $this->activityLogService->logOrderCreated($order);

        return redirect()->route('agencies.orders.complete')->with('success', '注文が正常に登録されました。');
    }

    public function store(Request $request)
    {
        $data = $request->all();
        $agencyId = auth('agencies')->user()->id;

        // Extract items (product_id and quantity)
        $items = [];
        foreach ($data as $key => $value) {
            if ($value == 0) {
                continue;
            }
            if (preg_match('/^item_number_(\d+)$/', $key, $matches)) {
                $productId = $matches[1];
                $quantity = (int) $value;
                $items[$productId] = $quantity;
            }
        }

        // Retrieve products from database and calculate total price
        $products = Product::whereIn('id', array_keys($items))->get();
        $totalPrice = 0;
        foreach ($products as $product) {
            $totalPrice += $product->price * $items[$product->id];
        }

        // Insert data into orders and order_details using a transaction
        DB::transaction(function () use ($agencyId, $totalPrice, $items, $products) {
            $status = 2;
            $merchantId = 0;

            // Insert into orders table
            $order = Order::create([
                'merchant_id' => $merchantId,
                'agency_id' => $agencyId,
                'total_price' => $totalPrice,
                'status' => $status,
            ]);

            // Insert into order_details table
            $orderDetails = [];
            foreach ($products as $product) {
                $orderDetails[] = [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $items[$product->id],
                    'price' => $product->price,
                ];
            }
            OrderDetail::insert($orderDetails);

            // ログを記録
            $this->activityLogService->logOrderCreated($order);
        });

        return redirect()->route('agencies.orders.success')->with('success', 'Order registered successfully.');
    }

    /**
     * 注文詳細を表示
     */
    public function show(Order $order)
    {
        $order->load('details.product', 'merchant', 'agency');

        return view('agencies.orders.show', compact('order'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        // バリデーション
        $validated = $request->validate([
            'status' => 'required|integer|in:1,2,3,9'
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
