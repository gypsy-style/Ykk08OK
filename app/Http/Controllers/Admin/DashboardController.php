<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', Carbon::now()->format('Y-m'));

        // ダッシュボード用のデータを取得する場合
        $data = [
            'agencyCount' => \App\Models\Agency::count(),
            'merchantCount' => \App\Models\Merchant::where('is_test', 0)->count(),
        ];

        // 各statusの件数を取得
        $statusCounts = DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereIn('status', [2, 3, 4, 5, 6, 9]) // 対象とするステータス
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->groupBy('status')
            ->pluck('count', 'status') // 結果を 'status' => 'count' の形式で取得
            ->toArray();
        // 全ステータスを初期化し、結果をマージして不足分を補完
        $statusCounts = array_replace([2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 9 => 0], $statusCounts);

        $headquartersProcessedQuery = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price, SUM(shipping_fee) as shipping_fee')
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            });
        InvoiceService::applyInvoiceScope($headquartersProcessedQuery);
        InvoiceService::applyInvoiceMonth($headquartersProcessedQuery, $month);
        $headquartersProcessed = $headquartersProcessedQuery->first();

        // shipping_fee が 0以上の件数を取得
        $shippingFeeCountQuery = DB::table('orders')
            ->where('shipping_fee', '>', 0)
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            });
        InvoiceService::applyInvoiceScope($shippingFeeCountQuery);
        InvoiceService::applyInvoiceMonth($shippingFeeCountQuery, $month);
        $shippingFeeCount = $shippingFeeCountQuery->count();

        // 商品別の月別売上集計
        $productSalesQuery = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->whereNotIn('o.merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            });
        InvoiceService::applyInvoiceScope($productSalesQuery, 'o');
        InvoiceService::applyInvoiceMonth($productSalesQuery, $month, 'o');
        $productSales = $productSalesQuery
            ->groupBy('p.id', 'p.product_name')
            ->orderByDesc(DB::raw('SUM(od.quantity * od.price)'))
            ->select(
                'p.id as product_id',
                'p.product_name',
                DB::raw('SUM(od.quantity) as total_quantity'),
                DB::raw('SUM(od.quantity * od.price) as total_amount')
            )
            ->get();


        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->subMonth()->format('Y-m');
        $nextMonth = $currentDate->addMonths(2)->format('Y-m');

        return view('admin.dashboard', compact('data', 'headquartersProcessed', 'shippingFeeCount', 'statusCounts', 'productSales', 'month', 'prevMonth', 'nextMonth'));
    }
}
