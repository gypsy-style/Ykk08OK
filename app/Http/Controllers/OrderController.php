<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\MerchantMember;
use App\Models\Order;
use App\Services\ActivityLogService;
use App\Services\EmailNotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    protected $activityLogService;
    protected $emailNotificationService;

    public function __construct(ActivityLogService $activityLogService, EmailNotificationService $emailNotificationService)
    {
        $this->activityLogService = $activityLogService;
        $this->emailNotificationService = $emailNotificationService;
    }

    /**
     * 一覧画面
     * @return View|Factory 
     * @throws BindingResolutionException 
     */
    public function list()
    {
        // 商品データを取得 (例: ページネーション)
        $categories = Category::with(['products' => function ($query) {
        $query->where('status', 'available')
              ->where(function($q){
                  $q->whereNull('single_sale_prohibited')->orWhere('single_sale_prohibited', 0);
              });
    }])->get();
        // 税込価格（10%）を表示用に付与
        $categories->each(function ($category) {
            $category->products->each(function ($product) {
                $product->price_with_tax = (int) round($product->price * 1.1);
            });
        });
        // dd($categories);

        // ビューにデータを渡す
        return view('order.list', compact('categories'));
    }

    /**
     * カード画面
     * @param Request $request 
     * @return View|Factory 
     * @throws BindingResolutionException 
     */
    public function register(Request $request)
    {
        // 1. リクエストデータを取得
        $data = $request->all();
        // line_idを取得
        $accessToken = $data['access_token'];
        $profile = $this->getLineProfile($accessToken);
        if ($profile) {
            $line_id = $profile['line_id'];
        } else {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }

        // ユーザー情報を渡す
        $user = User::where('line_id', $line_id)->first();
        if (!$user) {
            return redirect()->back()->withErrors(['line_id' => 'ユーザーが見つかりません。']);
        }
        $userId = $user->id;

        // 2. item_number_{商品ID} フォーマットのデータを抽出
        $items = [];
        foreach ($data as $key => $value) {
            if ($value == 0) {
                continue;
            }
            if (preg_match('/^item_number_(\d+)$/', $key, $matches)) {
                $productId = $matches[1];
                $quantity = (int)$value;
                $items[$productId] = $quantity; // 商品ID => 個数 の形式で保存
            }
        }



        // 3. 商品情報を取得し、価格の合計を計算
        $products = Product::whereIn('id', array_keys($items))
            ->with(['accessories'])
            ->get();
        $totalPrice = 0;
        $totalQuantity = 0;

        foreach ($products as $product) {
            $productId = $product->id;
            $quantity = $items[$productId];
            $actualQuantity = $quantity * $product->unit_quantity; // 注文個数 × 商品入数
            $product->quantity = $quantity; // 各商品の個数を追加
            $product->actual_quantity = $actualQuantity; // 実際の個数を追加
            $product->subtotal = $product->price * $quantity; // 小計を計算
            // 税込単価（10%）を表示用に付与
            $product->price_with_tax = (int) round($product->price * 1.1);
            $totalPrice += $product->subtotal; // 合計に加算
            $totalQuantity += $product->quantity; // 合計に加算
        }

        // 送料の計算（20,000円以下なら770円）
        $shippingFee = ($totalPrice <= 20000) ? 770 : 0;
        // 税込合計（10%）
        $grandTotalTaxIncluded = (int) round($totalPrice * 1.1) + $shippingFee;

        // 4. ビューに渡す
        return view('order.register', [
            'line_id' => $line_id,
            'products' => $products,
            'totalPrice' => $totalPrice,
            'shippingFee' => $shippingFee,
            'totalQuantity' => $totalQuantity,
            'user_id' => $userId,
            'grandTotalTaxIncluded' => $grandTotalTaxIncluded,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        Log::alert(print_r($data, true));

        // ユーザー取得
        $userId = $data['user_id'];
        $user = User::find($userId);
        
        // 備考
        $memo = $data['memo'];

        if (!$user) {
            return response()->json(['error' => 'ユーザーが見つかりません'], 404);
        }
        $merchant = Merchant::where('user_id', $userId)->first();
        if(!$merchant) {
            // オーナーではない場合
            $merchantMember = MerchantMember::where('user_id', $userId)->first();
            if (!$merchantMember) {
                return response()->json(['error' => '対応する店舗が見つかりません'], 404);
            }
            $merchant_id = $merchantMember->merchant_id;
            $merchant = Merchant::find($merchant_id);
        }

        if (!$merchant) {
            return response()->json(['error' => '対応する店舗が見つかりません'], 404);
        }

        $merchantId = $merchant->id;

        $agencyId = $merchant ? $merchant->agency_id : null;

        // 商品データ取得
        $items = [];
        foreach ($data as $key => $value) {
            if ($value == 0) continue;
            if (preg_match('/^item_number_(\d+)$/', $key, $matches)) {
                $productId = $matches[1];
                $items[$productId] = (int) $value;
            }
        }

        if (empty($items)) {
            return response()->json(['error' => '商品が選択されていません'], 400);
        }

        $products = Product::whereIn('id', array_keys($items))->with('accessories')->get();
        $totalPrice = 0;

        foreach ($products as $product) {
            $totalPrice += $product->price * $items[$product->id];
        }
        // 送料の計算（20,000円以下なら770円）
        $shippingFee = ($totalPrice <= 20000) ? 770 : 0;

        try {
            DB::beginTransaction();

            do {
                $orderNumber = Str::upper(Str::random(12)); // 12桁の英数字（大文字）
            } while (Order::where('order_number', $orderNumber)->exists());

            // 注文データを保存
            $order = Order::create([
                'order_number' => $orderNumber,
                'agency_id' => $agencyId,
                'merchant_id' => $merchantId,
                'user_id' => $userId,
                'total_price' => $totalPrice,
                'shipping_fee' => $shippingFee,
                'status' => 1, // 初期ステータス
                'memo' => $memo, 
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 注文詳細を保存
            foreach ($products as $product) {
                // メイン商品を登録（DBには実際の個数を保存）
                $actualQuantity = $items[$product->id];
                DB::table('order_details')->insert([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $actualQuantity, // 注文個数 × 商品入数
                    'price' => $product->price,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 付属商品があれば価格0で別レコードとして登録
                if ($product->accessories && $product->accessories->count() > 0) {
                    foreach ($product->accessories as $accessory) {
                        // 付属商品のID（FK）を利用して登録
                        $accessoryProductId = $accessory->accessory_product_id;
                        if ($accessoryProductId) {
                            DB::table('order_details')->insert([
                                'order_id' => $order->id,
                                'product_id' => $accessoryProductId,
                                'quantity' => $accessory->quantity * $actualQuantity, // メイン商品の実際数量 × 付属商品の数量
                                'price' => 0, // 付属商品は価格0
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }

            // 注文作成のログを記録
            $this->activityLogService->logOrderCreated($order);

            // メール通知
            $this->emailNotificationService->sendOrderNotification($order);

            DB::commit();

            return response()->json([
                'message' => '注文が正常に登録されました',
                'order_id' => $order->id,
                'total_price' => $totalPrice
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'サーバーエラーが発生しました',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function cancel(Request $request)
    {
        $order_id = $request->input('order_id');
        if(!$order_id) {
            return response()->json(['error' => 'Order not requested'], 404);   
        }
        
        $order = Order::findOrFail($order_id);
        if(!$order) {
            return response()->json(['error' => 'Order not found'], 404);   
        }

        // 変更前の値を保存
        $oldValues = $order->toArray();

        try {
            $order->update(['status' => 9]); // キャンセル

            // 変更後の値を取得
            $newValues = $order->toArray();

            // キャンセルのログを記録
            $this->activityLogService->log(
                'order_cancelled',
                $order,
                $oldValues,
                $newValues,
                '注文がキャンセルされました'
            );

            return response()->json([
                'success' => true,
            ]);
        } catch(\Exception $e) {
            return response()->json(['error'=>'error:'.$e->getMessage()],500);
        }
    }

    public function history(Request $request)
    {

        // HTML を生成して返す
        return view('order.history');
    }

    public function detail(Order $order)
    {
        $order->load('details.product', 'merchant', 'agency');
        // dd($order);
        return view('order.detail', compact('order'));
    }

    public function getOrderHistory(Request $request)
    {
        $data = $request->all();
        // リクエストのバリデーション
        $accessToken = $data['accessToken'];;
        $profile = $this->getLineProfile($accessToken);
        Log::alert('order line_ID:' . print_r($profile, true));
        if ($profile) {
            $lineId = $profile['line_id'];
        } else {
            return response()->json(['error' => 'User not found or invalid token'], 404);
        }


        // LINE ID でユーザーを取得
        $user = User::where('line_id', $lineId)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $merchant = Merchant::where('user_id', $user->id)->first();
        if(!$merchant) {
            // オーナーではない場合
            $merchantMember = MerchantMember::where('user_id', $user->id)->first();
            if (!$merchantMember) {
                return response()->json(['error' => '対応する店舗が見つかりません'], 404);
            }
            $merchant_id = $merchantMember->merchant_id;
            $merchant = Merchant::find($merchant_id);
        }

        if (!$merchant) {
            return response()->json(['error' => '対応する店舗が見つかりません'], 404);
        }

        $merchant_id = $merchant->id;

        // ユーザーIDから加盟店情報を取得

        // 加盟店ごとの注文履歴を取得
        $orders = Order::where('merchant_id', $merchant_id)
            ->with('details.product')
            ->orderBy('created_at', 'desc') // 追加: created_at を降順でソート
            ->get();

        // BladeでHTMLをレンダリング
        $html = $this->renderDetailHtml($orders);


        return response()->json(['html' => $html]);
    }

    public function renderDetailHtml($orders)
    {
        return view('order.partials.history', compact('orders'))->render();
    }
}
