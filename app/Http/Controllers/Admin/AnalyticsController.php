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
        $nav = $this->monthNav($month);
        $excludeTest = $this->excludeTest($request);

        return view('admin.analytics.index', array_merge(
            $nav,
            [
                'excludeTest' => $excludeTest,
                'overview' => SalonAnalyticsService::overview($excludeTest),
                'dormantSalons' => SalonAnalyticsService::dormantSalons($excludeTest),
                'newMerchants' => SalonAnalyticsService::monthlyNewMerchantsByAgency($nav['months'], $excludeTest),
            ],
            $this->productSalesData($month, self::TOP_ROWS, $excludeTest)
        ));
    }

    /** 代理店別新規加盟店数テーブルだけを差し替えるための部分HTML */
    public function newMerchants(Request $request)
    {
        $nav = $this->monthNav($this->month($request));
        $excludeTest = $this->excludeTest($request);

        return view('admin.analytics._new_merchants', array_merge($nav, [
            'excludeTest' => $excludeTest,
            'newMerchants' => SalonAnalyticsService::monthlyNewMerchantsByAgency($nav['months'], $excludeTest),
        ]));
    }

    /** 商品売上テーブルだけを差し替えるための部分HTML */
    public function productSales(Request $request)
    {
        $limit = $request->query('all') ? null : self::TOP_ROWS;

        return view('admin.analytics._product_sales', $this->productSalesData($this->month($request), $limit, $this->excludeTest($request)));
    }

    /** 全サロンを載せた一覧ページ */
    public function productSalesAll(Request $request)
    {
        return view('admin.analytics.product_sales', $this->productSalesData($this->month($request), null, $this->excludeTest($request)));
    }

    /** サロン1件の詳細 */
    public function salon(Request $request, $merchantId)
    {
        $month = $this->month($request);
        $nav = $this->monthNav($month);
        $detail = SalonAnalyticsService::salonDetail($merchantId, $nav['months']);

        if ($detail === null) {
            abort(404);
        }

        return view('admin.analytics.salon', array_merge(['detail' => $detail], $nav));
    }

    /** 代理店1件の詳細 */
    public function agency(Request $request, $agencyId)
    {
        $month = $this->month($request);
        $nav = $this->monthNav($month);
        $added = $request->query('added');
        $detail = SalonAnalyticsService::agencyDetail(
            $agencyId,
            $nav['months'],
            is_string($added) && preg_match('/\A\d{4}-\d{2}\z/', $added) ? $added : null
        );

        if ($detail === null) {
            abort(404);
        }

        return view('admin.analytics.agency', array_merge(['detail' => $detail], $nav));
    }

    /** 6ヶ月表示と先月・次月リンクに必要な値 */
    private function monthNav($month)
    {
        $date = Carbon::parse($month . '-01');

        return [
            'months' => SalonAnalyticsService::months($month),
            'month' => $month,
            'prevMonth' => $date->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'hasNextMonth' => $month < Carbon::now()->format('Y-m'),
        ];
    }

    private function month(Request $request)
    {
        $month = $request->query('month');
        if (!is_string($month) || !preg_match('/\A\d{4}-\d{2}\z/', $month)) {
            return Carbon::now()->format('Y-m');
        }

        return $month;
    }

    /** 未指定ならテストを除外する */
    private function excludeTest(Request $request)
    {
        return $request->query('exclude_test', '1') !== '0';
    }

    private function productSalesData($month, $limit, $excludeTest)
    {
        $date = Carbon::parse($month . '-01');
        $sales = SalonAnalyticsService::salonProductSales($month, $excludeTest);

        $hasMore = $limit !== null && count($sales['rows']) > $limit;
        if ($limit !== null) {
            $sales['rows'] = array_slice($sales['rows'], 0, $limit);
        }

        return [
            'excludeTest' => $excludeTest,
            'productSales' => $sales,
            'productMonth' => $month,
            'productPrevMonth' => $date->copy()->subMonth()->format('Y-m'),
            'productNextMonth' => $date->copy()->addMonth()->format('Y-m'),
            'productHasNextMonth' => $month < Carbon::now()->format('Y-m'),
            'productHasMore' => $hasMore,
        ];
    }
}
