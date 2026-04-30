<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    /** 売上集計対象のステータス（保留=4以外の確定注文） */
    private const SALES_STATUSES = [2, 3, 5, 6];

    public function index(Request $request)
    {
        $month = $request->query('month', Carbon::now()->format('Y-m'));

        // 商品別の月別売上集計
        $productSales = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->whereIn('o.status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(o.created_at, "%Y-%m") = ?', [$month])
            ->groupBy('p.id', 'p.product_name')
            ->orderByDesc(DB::raw('SUM(od.quantity * od.price)'))
            ->select(
                'p.id as product_id',
                'p.product_name',
                DB::raw('SUM(od.quantity) as total_quantity'),
                DB::raw('SUM(od.quantity * od.price) as total_amount')
            )
            ->get();

        $headquartersProcessed = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price, SUM(shipping_fee) as shipping_fee')
            ->whereIn('status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->first();

        $shippingFeeCount = DB::table('orders')
            ->where('shipping_fee', '>', 0)
            ->whereIn('status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->count();

        // 月内に売上があった店舗一覧
        $merchantSales = DB::table('orders as o')
            ->join('merchants as m', 'm.id', '=', 'o.merchant_id')
            ->leftJoin('agencies as a', 'a.id', '=', 'm.agency_id')
            ->whereIn('o.status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(o.created_at, "%Y-%m") = ?', [$month])
            ->groupBy('m.id', 'm.name', 'm.member_rank', 'a.name')
            ->orderByDesc(DB::raw('SUM(o.total_price + o.shipping_fee)'))
            ->select(
                'm.id as merchant_id',
                'm.name as merchant_name',
                'm.member_rank',
                'a.name as agency_name',
                DB::raw('COUNT(o.id) as order_count'),
                DB::raw('SUM(o.total_price + o.shipping_fee) as total_amount')
            )
            ->get();

        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        $grandTotal = ((int) $productSales->sum('total_amount'))
            + ((int) ($headquartersProcessed->shipping_fee ?? 0));

        return view('admin.sales.index', compact(
            'productSales',
            'merchantSales',
            'headquartersProcessed',
            'shippingFeeCount',
            'grandTotal',
            'month',
            'prevMonth',
            'nextMonth'
        ));
    }

    public function show($merchantId, Request $request)
    {
        $merchant = Merchant::with('agency')->findOrFail($merchantId);

        $month = $request->query('month', Carbon::now()->format('Y-m'));
        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        // 月内のこの店舗の注文を全件取得
        $orders = Order::with('details.product')
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->orderBy('created_at', 'desc')
            ->get();

        // 月内全注文を商品ごとに合算
        $productAgg = [];
        $monthSubtotal = 0;
        $monthShippingFee = 0;
        foreach ($orders as $order) {
            $monthSubtotal += (int) ($order->total_price ?? 0);
            $monthShippingFee += (int) ($order->shipping_fee ?? 0);
            foreach ($order->details as $detail) {
                $pid = $detail->product_id;
                if (!isset($productAgg[$pid])) {
                    $productAgg[$pid] = [
                        'name' => optional($detail->product)->product_name ?? '',
                        'quantity' => 0,
                        'amount' => 0,
                    ];
                }
                $productAgg[$pid]['quantity'] += (int) $detail->quantity;
                $productAgg[$pid]['amount'] += (int) round($detail->price * $detail->quantity);
            }
        }
        uasort($productAgg, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        $monthTaxAmount = (int) round($monthSubtotal * 1.1) - $monthSubtotal;
        $monthGrandTotal = (int) round($monthSubtotal * 1.1) + $monthShippingFee;
        $monthTotalQuantity = array_sum(array_column($productAgg, 'quantity'));
        $monthOrderCount = $orders->count();

        // 合算コピー用テキスト
        $lines = [];
        $lines[] = $merchant->name ?? '';
        if ($merchant->postal_code1 && $merchant->postal_code2) {
            $lines[] = $merchant->postal_code1 . '-' . $merchant->postal_code2;
        }
        $lines[] = $merchant->address ?? '';
        $lines[] = $merchant->phone ?? '';
        $lines[] = '';
        // $lines[] = Carbon::parse($month . '-01')->format('Y年m月') . ' 受注合算（' . $monthOrderCount . '件）';
        foreach ($productAgg as $row) {
            $lines[] = $row['name'] . '×' . $row['quantity'] . '個 ' . number_format($row['amount']) . '円';
        }
        $lines[] = '送料 ' . number_format($monthShippingFee) . '円';
        $lines[] = '消費税 ' . number_format($monthTaxAmount) . '円';
        $lines[] = '合計 ' . $monthTotalQuantity . '個 ' . number_format($monthGrandTotal) . '円';
        $copyText = implode("\n", $lines);

        return view('admin.sales.show', compact(
            'merchant',
            'orders',
            'productAgg',
            'monthSubtotal',
            'monthShippingFee',
            'monthTaxAmount',
            'monthGrandTotal',
            'monthTotalQuantity',
            'monthOrderCount',
            'copyText',
            'month',
            'prevMonth',
            'nextMonth'
        ));
    }
}
