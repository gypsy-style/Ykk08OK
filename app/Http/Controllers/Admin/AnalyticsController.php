<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalonAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $month = $this->month($request);
        $months = SalonAnalyticsService::months($month);
        $newMerchants = SalonAnalyticsService::monthlyNewMerchants($months);

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
        ), $this->productSalesData($month)));
    }

    /** 商品売上テーブルだけを差し替えるための部分HTML */
    public function productSales(Request $request)
    {
        return view('admin.analytics._product_sales', $this->productSalesData($this->month($request)));
    }

    private function month(Request $request)
    {
        $month = $request->query('month');
        if (!is_string($month) || !preg_match('/\A\d{4}-\d{2}\z/', $month)) {
            return Carbon::now()->format('Y-m');
        }

        return $month;
    }

    private function productSalesData($month)
    {
        $date = Carbon::parse($month . '-01');

        return [
            'productSales' => SalonAnalyticsService::salonProductSales($month),
            'productMonth' => $month,
            'productPrevMonth' => $date->copy()->subMonth()->format('Y-m'),
            'productNextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'productHasNextMonth' => $month < Carbon::now()->format('Y-m'),
        ];
    }
}
