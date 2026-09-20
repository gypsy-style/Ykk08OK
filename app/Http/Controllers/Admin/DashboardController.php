<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\InvoiceService;
use App\Services\TestDataFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', Carbon::now()->format('Y-m'));
        // 未指定ならテストを除外する
        $excludeTest = $request->query('exclude_test', '1') !== '0';

        $agencyCountQuery = \App\Models\Agency::query();
        if ($excludeTest) {
            TestDataFilter::excludeAgencyRows($agencyCountQuery);
        }

        $merchantCountQuery = \App\Models\Merchant::query();
        if ($excludeTest) {
            TestDataFilter::excludeMerchantRows($merchantCountQuery);
        }

        // ダッシュボード用のデータを取得する場合
        $data = [
            'agencyCount' => $agencyCountQuery->count(),
            'merchantCount' => $merchantCountQuery->count(),
        ];

        // 各statusの件数を取得
        $statusCountsQuery = DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereIn('status', [2, 3, 4, 5, 6, 9]); // 対象とするステータス
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($statusCountsQuery);
        }
        $statusCounts = $statusCountsQuery
            ->groupBy('status')
            ->pluck('count', 'status') // 結果を 'status' => 'count' の形式で取得
            ->toArray();
        // 全ステータスを初期化し、結果をマージして不足分を補完
        $statusCounts = array_replace([2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 9 => 0], $statusCounts);

        $headquartersProcessedQuery = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price, SUM(shipping_fee) as shipping_fee');
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($headquartersProcessedQuery);
        }
        InvoiceService::applyInvoiceScope($headquartersProcessedQuery);
        InvoiceService::applyInvoiceMonth($headquartersProcessedQuery, $month);
        $headquartersProcessed = $headquartersProcessedQuery->first();

        // shipping_fee が 0以上の件数を取得
        $shippingFeeCountQuery = DB::table('orders')
            ->where('shipping_fee', '>', 0);
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($shippingFeeCountQuery);
        }
        InvoiceService::applyInvoiceScope($shippingFeeCountQuery);
        InvoiceService::applyInvoiceMonth($shippingFeeCountQuery, $month);
        $shippingFeeCount = $shippingFeeCountQuery->count();

        // 商品別の月別売上集計
        $productSalesQuery = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id');
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($productSalesQuery, 'o');
        }
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

        $date = $this->date($request);
        $daily = $this->dailyReport($date, $excludeTest);
        $day = Carbon::parse($date);
        $prevDate = $day->copy()->subDay()->format('Y-m-d');
        $nextDate = $day->copy()->addDay()->format('Y-m-d');
        $hasNextDate = $date < Carbon::now()->format('Y-m-d');

        return view('admin.dashboard', compact('data', 'headquartersProcessed', 'shippingFeeCount', 'statusCounts', 'productSales', 'month', 'prevMonth', 'nextMonth', 'excludeTest', 'daily', 'date', 'prevDate', 'nextDate', 'hasNextDate'));
    }

    /** 日報で表示する日。未指定・不正なら今日 */
    private function date(Request $request)
    {
        $date = $request->query('date');
        if (!is_string($date) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) {
            return Carbon::now()->format('Y-m-d');
        }

        return $date;
    }

    /**
     * 指定日の日報（商品別売上と送料）
     *
     * 計上日の基準は月報と同じで発送日。月報側の集計には手を入れたくないので、
     * 日報は独立した問い合わせにしている。
     *
     * @param string $date YYYY-MM-DD
     * @return array
     */
    private function dailyReport($date, $excludeTest)
    {
        $productSalesQuery = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id');
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($productSalesQuery, 'o');
        }
        InvoiceService::applyInvoiceScope($productSalesQuery, 'o');
        InvoiceService::applyInvoiceDate($productSalesQuery, $date, 'o');
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

        // 送料は注文単位。明細と JOIN すると件数も金額も膨らむため別に集計する。
        $ordersQuery = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(shipping_fee) as shipping_fee, SUM(CASE WHEN shipping_fee > 0 THEN 1 ELSE 0 END) as shipping_count');
        if ($excludeTest) {
            TestDataFilter::excludeMerchants($ordersQuery);
        }
        InvoiceService::applyInvoiceScope($ordersQuery);
        InvoiceService::applyInvoiceDate($ordersQuery, $date);
        $orders = $ordersQuery->first();

        $shippingFee = (int) ($orders->shipping_fee ?? 0);

        return [
            'productSales' => $productSales,
            'orderCount' => (int) ($orders->order_count ?? 0),
            'shippingCount' => (int) ($orders->shipping_count ?? 0),
            'shippingFee' => $shippingFee,
            'total' => (int) $productSales->sum('total_amount') + $shippingFee,
        ];
    }
}
