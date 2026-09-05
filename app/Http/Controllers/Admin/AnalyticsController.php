<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalonAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /** サロン分析のトップに出す行数。これを超える分は一覧ページで見る */
    private const TOP_ROWS = 10;

    public function index(Request $request)
    {
        $month = $this->month($request);
        $months = SalonAnalyticsService::months($month);
        $newMerchants = SalonAnalyticsService::monthlyNewMerchantsByAgency($months);

        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');
        $hasNextMonth = $month < Carbon::now()->format('Y-m');

        return view('admin.analytics.index', array_merge(compact(
            'months',
            'newMerchants',
            'month',
            'prevMonth',
            'nextMonth',
            'hasNextMonth'
        ), $this->productSalesData($month, self::TOP_ROWS)));
    }

    /** 商品売上テーブルだけを差し替えるための部分HTML */
    public function productSales(Request $request)
    {
        $limit = $request->query('all') ? null : self::TOP_ROWS;

        return view('admin.analytics._product_sales', $this->productSalesData($this->month($request), $limit));
    }

    /** 全サロンを載せた一覧ページ */
    public function productSalesAll(Request $request)
    {
        return view('admin.analytics.product_sales', $this->productSalesData($this->month($request), null));
    }

    private function month(Request $request)
    {
        $month = $request->query('month');
        if (!is_string($month) || !preg_match('/\A\d{4}-\d{2}\z/', $month)) {
            return Carbon::now()->format('Y-m');
        }

        return $month;
    }

    private function productSalesData($month, $limit)
    {
        $date = Carbon::parse($month . '-01');
        $sales = SalonAnalyticsService::salonProductSales($month);

        $hasMore = $limit !== null && count($sales['rows']) > $limit;
        if ($limit !== null) {
            $sales['rows'] = array_slice($sales['rows'], 0, $limit);
        }

        return [
            'productSales' => $sales,
            'productMonth' => $month,
            'productPrevMonth' => $date->copy()->subMonth()->format('Y-m'),
            'productNextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'productHasNextMonth' => $month < Carbon::now()->format('Y-m'),
            'productHasMore' => $hasMore,
        ];
    }
}
