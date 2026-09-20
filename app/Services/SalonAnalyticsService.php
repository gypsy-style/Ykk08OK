<?php

namespace App\Services;

use App\Models\Agency;
use App\Models\Merchant;
use App\Services\TestDataFilter;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * サロン分析の集計ロジック
 *
 * 発送済みの判定は InvoiceService に委譲する。請求書と分析で基準がずれないよう、
 * 計上月の定義を変えるときは InvoiceService だけを直すこと。
 */
class SalonAnalyticsService
{
    /** 表示する月数 */
    public const MONTHS = 6;

    /**
     * 終点の月から遡って MONTHS ヶ月分の 'YYYY-MM' を古い順で返す
     *
     * @param string $endMonth YYYY-MM
     * @return array<int, string>
     */
    public static function months($endMonth)
    {
        $end = Carbon::parse($endMonth . '-01')->startOfMonth();
        $months = [];

        for ($i = self::MONTHS - 1; $i >= 0; $i--) {
            $months[] = $end->copy()->subMonths($i)->format('Y-m');
        }

        return $months;
    }

    /** 代理店に紐づかない加盟店をまとめる行のキー */
    private const NO_AGENCY = 0;

    /**
     * 代理店ごと・月ごとの新規加盟店数
     *
     * @param array<int, string> $months
     * @return array{rows: array<int, array{id: int, name: string, byMonth: array<string, int>}>, totals: array<string, int>}
     */
    public static function monthlyNewMerchantsByAgency(array $months, $excludeTest = true)
    {
        [$start, $end] = self::range($months);

        // deleted_at で絞らない。絞ると過去月の新規加盟店数が、後からサロンを
        // 削除するたびに変わってしまい過去の数字として使えなくなる。
        //
        // 絞り込みは素の日時比較で行う。DATE_FORMAT を WHERE 句に書くと
        // インデックスが効かないため、月への振り分けにだけ使う。
        $counts = [];
        $merchantsQuery = DB::table('merchants');
        if ($excludeTest) {
            TestDataFilter::excludeMerchantRows($merchantsQuery);
        }
        $rows = $merchantsQuery
            ->selectRaw('agency_id, DATE_FORMAT(created_at, "%Y-%m") as ym, COUNT(*) as cnt')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->groupBy('agency_id', 'ym')
            ->get();
        foreach ($rows as $row) {
            $key = $row->agency_id === null ? self::NO_AGENCY : (int) $row->agency_id;
            $counts[$key][$row->ym] = (int) $row->cnt;
        }

        $agenciesQuery = Agency::orderBy('name');
        if ($excludeTest) {
            TestDataFilter::excludeAgencyRows($agenciesQuery);
        }
        $agencies = $agenciesQuery->get(['id', 'name'])->all();
        // 代理店に紐づかない加盟店は該当期間にいるときだけ末尾に出す
        if (!empty($counts[self::NO_AGENCY])) {
            $agencies[] = (object) ['id' => self::NO_AGENCY, 'name' => '代理店なし'];
        }

        $totals = array_fill_keys($months, 0);
        $result = [];
        foreach ($agencies as $agency) {
            $byMonth = [];
            foreach ($months as $month) {
                $count = $counts[$agency->id][$month] ?? 0;
                $byMonth[$month] = $count;
                $totals[$month] += $count;
            }

            $result[] = ['id' => $agency->id, 'name' => $agency->name, 'byMonth' => $byMonth];
        }

        return ['rows' => $result, 'totals' => $totals];
    }

    /**
     * サロン×商品の売上（税込・単月）
     *
     * 列に出す商品はその月に売上があったものだけ。付属商品は price 0 で登録されるため
     * ここでも金額0として除かれ、0だけの列は並ばない。
     *
     * @param string $month YYYY-MM
     * @return array{products: array<int, array{id: int, name: string}>, rows: array<int, array{id: int, name: string, deleted: bool, shipments: int, byProduct: array<int, int>, byQuantity: array<int, int>, quantity: int, total: int}>}
     */
    public static function salonProductSales($month, $excludeTest = true)
    {
        [$start, $end] = self::range([$month]);

        $query = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->selectRaw('o.merchant_id, p.id as product_id, p.product_name, SUM(od.quantity * od.price) as subtotal, SUM(od.quantity) as quantity')
            ->where('o.shipped_at', '>=', $start)
            ->where('o.shipped_at', '<', $end)
            ->groupBy('o.merchant_id', 'p.id', 'p.product_name');

        if ($excludeTest) {
            TestDataFilter::excludeMerchants($query, 'o');
        }

        InvoiceService::applyInvoiceScope($query, 'o');

        $sales = [];
        $quantities = [];
        $names = [];
        $totals = [];
        foreach ($query->get() as $row) {
            $amount = (int) round($row->subtotal * 1.1);
            if ($amount === 0) {
                continue;
            }
            $sales[$row->merchant_id][$row->product_id] = $amount;
            $quantities[$row->merchant_id][$row->product_id] = (int) $row->quantity;
            $names[$row->product_id] = $row->product_name;
            $totals[$row->product_id] = ($totals[$row->product_id] ?? 0) + $amount;
        }

        $shipments = self::shipmentCounts($start, $end, $excludeTest);

        uksort($totals, function ($a, $b) use ($totals, $names) {
            return $totals[$b] <=> $totals[$a] ?: strcmp($names[$a], $names[$b]);
        });

        $products = [];
        foreach ($totals as $id => $total) {
            $products[] = ['id' => $id, 'name' => $names[$id]];
        }

        $rows = [];
        foreach (self::merchants($excludeTest) as $merchant) {
            $byProduct = [];
            $byQuantity = [];
            $total = 0;
            $quantity = 0;
            foreach ($products as $product) {
                $amount = $sales[$merchant->id][$product['id']] ?? 0;
                $count = $quantities[$merchant->id][$product['id']] ?? 0;
                $byProduct[$product['id']] = $amount;
                $byQuantity[$product['id']] = $count;
                $total += $amount;
                $quantity += $count;
            }

            if ($merchant->deleted_at !== null && $total === 0) {
                continue;
            }

            $rows[] = [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'deleted' => $merchant->deleted_at !== null,
                'shipments' => $shipments[$merchant->id] ?? 0,
                'byProduct' => $byProduct,
                'byQuantity' => $byQuantity,
                'quantity' => $quantity,
                'total' => $total,
            ];
        }

        usort($rows, function ($a, $b) {
            return $b['total'] <=> $a['total'] ?: strcmp($a['name'], $b['name']);
        });

        return ['products' => $products, 'rows' => $rows];
    }

    /**
     * サロンごとの発送件数。商品明細と JOIN すると件数が明細数だけ膨らむので別に数える。
     *
     * @return array<int, int> merchant_id => 件数
     */
    private static function shipmentCounts(Carbon $start, Carbon $end, $excludeTest)
    {
        $query = DB::table('orders as o')
            ->selectRaw('o.merchant_id, COUNT(*) as cnt')
            ->where('o.shipped_at', '>=', $start)
            ->where('o.shipped_at', '<', $end)
            ->groupBy('o.merchant_id');

        if ($excludeTest) {
            TestDataFilter::excludeMerchants($query, 'o');
        }

        InvoiceService::applyInvoiceScope($query, 'o');

        $counts = [];
        foreach ($query->get() as $row) {
            $counts[(int) $row->merchant_id] = (int) $row->cnt;
        }

        return $counts;
    }

    /**
     * 全サロンをまとめたサマリー（税込）
     *
     * 加盟店数は削除済みを除いた現在の数。売上は削除済みサロンの分も含める。
     *
     * @return array{merchantCount: int, grandTotal: int, averageMonthly: int, firstMonth: string|null, monthCount: int}
     */
    public static function overview($excludeTest = true)
    {
        $summary = self::summary(self::merchants($excludeTest)->pluck('id')->all(), []);

        $merchantCountQuery = Merchant::query();
        if ($excludeTest) {
            TestDataFilter::excludeMerchantRows($merchantCountQuery);
        }

        return [
            'merchantCount' => $merchantCountQuery->count(),
            'grandTotal' => $summary['grandTotal'],
            'averageMonthly' => $summary['averageMonthly'],
            'firstMonth' => $summary['firstMonth'],
            'monthCount' => $summary['monthCount'],
        ];
    }

    /**
     * サロン1件の詳細集計（税込）
     *
     * @param int $merchantId
     * @param array<int, string> $months 月別テーブルに出す 'YYYY-MM'
     * @return array|null 対象サロンが無ければ null
     */
    public static function salonDetail($merchantId, array $months)
    {
        $merchant = Merchant::withTrashed()->with('agency')->find($merchantId);
        if ($merchant === null) {
            return null;
        }

        return array_merge(self::summary([$merchant->id], $months), [
            'merchant' => [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'deleted' => $merchant->deleted_at !== null,
                'agencyName' => $merchant->agency === null ? '代理店なし' : $merchant->agency->name,
            ],
        ]);
    }

    /**
     * 代理店1件の詳細集計（税込）。配下サロンの売上をまとめて集計する。
     *
     * @param int $agencyId
     * @param array<int, string> $months 月別テーブルに出す 'YYYY-MM'
     * @param string|null $addedMonth 指定するとサロン一覧をその月の追加分だけに絞る 'YYYY-MM'
     * @return array|null 対象代理店が無ければ null
     */
    public static function agencyDetail($agencyId, array $months, $addedMonth = null)
    {
        $agency = Agency::find($agencyId);
        if ($agency === null) {
            return null;
        }

        // 削除済みサロンも含める。過去の売上が代理店の累計から消えないようにするため。
        $merchants = Merchant::withTrashed()
            ->where('agency_id', $agency->id)
            ->get(['id', 'name', 'deleted_at', 'created_at']);

        // 売上の集計は絞り込みに関係なく代理店全体で出す
        $summary = self::summary($merchants->pluck('id')->all(), $months);

        $salons = [];
        foreach ($merchants as $merchant) {
            $addedAt = $merchant->created_at === null ? null : $merchant->created_at->format('Y-m');
            if ($addedMonth !== null && $addedAt !== $addedMonth) {
                continue;
            }

            $salons[] = [
                'id' => $merchant->id,
                'name' => $merchant->name,
                'deleted' => $merchant->deleted_at !== null,
                'addedAt' => $addedAt,
                'total' => $summary['byMerchant'][$merchant->id] ?? 0,
            ];
        }

        usort($salons, function ($a, $b) {
            return $b['total'] <=> $a['total'] ?: strcmp($a['name'], $b['name']);
        });

        return array_merge($summary, [
            'agency' => ['id' => $agency->id, 'name' => $agency->name],
            'salons' => $salons,
            'addedMonth' => $addedMonth,
        ]);
    }

    /**
     * 指定サロンをまとめた月別・商品別の集計（税込）
     *
     * 金額は「月×サロン×商品」のセル単位で税込に丸め、累計も平均もその合計から出す。
     * 画面内のどの数字を足しても合うようにするため、丸めの単位を1か所に揃えている。
     *
     * @param array<int, int> $merchantIds
     * @param array<int, string> $months
     * @return array
     */
    private static function summary(array $merchantIds, array $months)
    {
        $query = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->selectRaw('DATE_FORMAT(o.shipped_at, "%Y-%m") as ym, o.merchant_id, p.id as product_id, p.product_name, SUM(od.quantity * od.price) as subtotal')
            ->whereIn('o.merchant_id', $merchantIds)
            ->groupBy('ym', 'o.merchant_id', 'p.id', 'p.product_name');

        InvoiceService::applyInvoiceScope($query, 'o');

        $sales = [];
        $names = [];
        $productTotals = [];
        $monthTotals = [];
        $byMerchant = [];
        foreach ($query->get() as $row) {
            $amount = (int) round($row->subtotal * 1.1);
            if ($amount === 0) {
                continue;
            }
            $sales[$row->ym][$row->product_id] = ($sales[$row->ym][$row->product_id] ?? 0) + $amount;
            $names[$row->product_id] = $row->product_name;
            $productTotals[$row->product_id] = ($productTotals[$row->product_id] ?? 0) + $amount;
            $monthTotals[$row->ym] = ($monthTotals[$row->ym] ?? 0) + $amount;
            $byMerchant[$row->merchant_id] = ($byMerchant[$row->merchant_id] ?? 0) + $amount;
        }

        uksort($productTotals, function ($a, $b) use ($productTotals, $names) {
            return $productTotals[$b] <=> $productTotals[$a] ?: strcmp($names[$a], $names[$b]);
        });

        // 列は全期間で売上のあった商品に固定する。月を移動するたびに列が
        // 増減すると、月をまたいだ比較ができなくなるため。
        $products = [];
        foreach ($productTotals as $id => $total) {
            $products[] = ['id' => $id, 'name' => $names[$id], 'total' => $total];
        }

        $monthly = [];
        foreach ($months as $month) {
            $byProduct = [];
            foreach ($products as $product) {
                $byProduct[$product['id']] = $sales[$month][$product['id']] ?? 0;
            }
            $monthly[$month] = [
                'byProduct' => $byProduct,
                'total' => $monthTotals[$month] ?? 0,
            ];
        }

        // 平均は初回売上月から当月までで割る。売上0の月も分母に含めないと、
        // 動きの止まったサロンほど平均が高く見えてしまう。
        ksort($monthTotals);
        $firstMonth = empty($monthTotals) ? null : array_key_first($monthTotals);
        $monthCount = $firstMonth === null
            ? 0
            : Carbon::parse($firstMonth . '-01')->diffInMonths(Carbon::now()->startOfMonth()) + 1;

        return [
            'products' => $products,
            'byMerchant' => $byMerchant,
            'monthly' => $monthly,
            'grandTotal' => array_sum($productTotals),
            'firstMonth' => $firstMonth,
            'monthCount' => $monthCount,
            'averageMonthly' => $monthCount === 0 ? 0 : (int) round(array_sum($productTotals) / $monthCount),
        ];
    }

    /**
     * 集計対象のサロン。削除済みも含めるのは、期間内に売上があるサロンを落とさないため。
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private static function merchants($excludeTest = true)
    {
        $query = Merchant::withTrashed();
        if ($excludeTest) {
            TestDataFilter::excludeMerchantRows($query);
        }

        return $query->orderBy('name')->get(['id', 'name', 'deleted_at']);
    }

    /**
     * 月の配列から [開始日時, 終了日時) を返す
     *
     * @param array<int, string> $months
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function range(array $months)
    {
        return [
            Carbon::parse($months[0] . '-01')->startOfMonth(),
            Carbon::parse(end($months) . '-01')->startOfMonth()->addMonth(),
        ];
    }
}
