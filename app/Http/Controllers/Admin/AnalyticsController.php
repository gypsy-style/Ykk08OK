<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SalonAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        return view('admin.analytics.salon', array_merge([
            'detail' => $detail,
            'dailySales' => SalonAnalyticsService::salonDailySales($merchantId, $month),
        ], $nav));
    }

    /**
     * 画面のテーブルをCSVで落とす。月・テスト除外は画面と同じ条件を使う
     *
     * Excelでそのまま開けるよう、BOM付きUTF-8で出す。
     * 商品売上は画面の上位10件に限らず全サロンを出す。
     */
    public function csv(Request $request, $type)
    {
        $month = $this->month($request);
        $excludeTest = $this->excludeTest($request);

        switch ($type) {
            case 'product-sales':
                [$filename, $rows] = $this->productSalesCsv($month, $excludeTest);
                break;
            case 'new-merchants':
                [$filename, $rows] = $this->newMerchantsCsv($month, $excludeTest);
                break;
            case 'last-orders':
                [$filename, $rows] = $this->lastOrdersCsv($excludeTest);
                break;
            default:
                abort(404);
        }

        return new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($handle, array_map([$this, 'csvCell'], $row));
            }
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function productSalesCsv($month, $excludeTest)
    {
        $sales = SalonAnalyticsService::salonProductSales($month, $excludeTest);

        $header = ['サロン名', '削除済み', '件数'];
        foreach ($sales['products'] as $product) {
            $header[] = $product['name'] . '（個数）';
            $header[] = $product['name'] . '（売上）';
        }
        $header[] = '合計（個数）';
        $header[] = '合計（売上）';

        $rows = [$header];
        foreach ($sales['rows'] as $row) {
            $line = [$row['name'], $row['deleted'] ? '削除済み' : '', $row['shipments']];
            foreach ($sales['products'] as $product) {
                $line[] = $row['byQuantity'][$product['id']];
                $line[] = $row['byProduct'][$product['id']];
            }
            $line[] = $row['quantity'];
            $line[] = $row['total'];
            $rows[] = $line;
        }

        return ['product_sales_' . $month . '.csv', $rows];
    }

    private function newMerchantsCsv($month, $excludeTest)
    {
        $months = SalonAnalyticsService::months($month);
        $data = SalonAnalyticsService::monthlyNewMerchantsByAgency($months, $excludeTest);

        $rows = [array_merge(['代理店名'], $months)];
        foreach ($data['rows'] as $row) {
            $rows[] = array_merge([$row['name']], array_values($row['byMonth']));
        }
        $rows[] = array_merge(['合計'], array_values($data['totals']));

        return ['new_merchants_' . $months[0] . '_' . $month . '.csv', $rows];
    }

    private function lastOrdersCsv($excludeTest)
    {
        $rows = [['サロン名', '最終購入日']];
        foreach (SalonAnalyticsService::lastOrderDates($excludeTest) as $row) {
            $rows[] = [$row['name'], $row['last'] === null ? '' : $row['last']->format('Y-m-d')];
        }

        return ['last_orders_' . Carbon::now()->format('Ymd') . '.csv', $rows];
    }

    /** Excelで式として解釈されないよう、=+-@ で始まる文字列の頭に ' を付ける */
    private function csvCell($value)
    {
        if (is_string($value) && $value !== '' && strpbrk($value[0], '=+-@') !== false) {
            return "'" . $value;
        }

        return $value;
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
