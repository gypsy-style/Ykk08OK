<?php

namespace App\Services;

use App\Models\Merchant;
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

    /**
     * 月ごとの新規加盟店数
     *
     * @param array<int, string> $months
     * @return array<string, int>
     */
    public static function monthlyNewMerchants(array $months)
    {
        [$start, $end] = self::range($months);

        // deleted_at で絞らない。絞ると過去月の新規加盟店数が、後からサロンを
        // 削除するたびに変わってしまい過去の数字として使えなくなる。
        $counts = self::countByMonth(
            DB::table('merchants')->where('is_test', 0),
            $start,
            $end
        );

        $result = [];
        foreach ($months as $month) {
            $result[$month] = (int) ($counts[$month] ?? 0);
        }

        return $result;
    }

    /**
     * サロン×商品の売上（税込・単月）
     *
     * 列に出す商品はその月に売上があったものだけ。付属商品は price 0 で登録されるため
     * ここでも金額0として除かれ、0だけの列は並ばない。
     *
     * @param string $month YYYY-MM
     * @return array{products: array<int, array{id: int, name: string}>, rows: array<int, array{name: string, deleted: bool, byProduct: array<int, int>, total: int}>}
     */
    public static function salonProductSales($month)
    {
        [$start, $end] = self::range([$month]);

        $query = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->selectRaw('o.merchant_id, p.id as product_id, p.product_name, SUM(od.quantity * od.price) as subtotal')
            ->whereNotIn('o.merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->where('o.shipped_at', '>=', $start)
            ->where('o.shipped_at', '<', $end)
            ->groupBy('o.merchant_id', 'p.id', 'p.product_name');

        InvoiceService::applyInvoiceScope($query, 'o');

        $sales = [];
        $names = [];
        $totals = [];
        foreach ($query->get() as $row) {
            $amount = (int) round($row->subtotal * 1.1);
            if ($amount === 0) {
                continue;
            }
            $sales[$row->merchant_id][$row->product_id] = $amount;
            $names[$row->product_id] = $row->product_name;
            $totals[$row->product_id] = ($totals[$row->product_id] ?? 0) + $amount;
        }

        uksort($totals, function ($a, $b) use ($totals, $names) {
            return $totals[$b] <=> $totals[$a] ?: strcmp($names[$a], $names[$b]);
        });

        $products = [];
        foreach ($totals as $id => $total) {
            $products[] = ['id' => $id, 'name' => $names[$id]];
        }

        $rows = [];
        foreach (self::merchants() as $merchant) {
            $byProduct = [];
            $total = 0;
            foreach ($products as $product) {
                $amount = $sales[$merchant->id][$product['id']] ?? 0;
                $byProduct[$product['id']] = $amount;
                $total += $amount;
            }

            if ($merchant->deleted_at !== null && $total === 0) {
                continue;
            }

            $rows[] = [
                'name' => $merchant->name,
                'deleted' => $merchant->deleted_at !== null,
                'byProduct' => $byProduct,
                'total' => $total,
            ];
        }

        usort($rows, function ($a, $b) {
            return $b['total'] <=> $a['total'] ?: strcmp($a['name'], $b['name']);
        });

        return ['products' => $products, 'rows' => $rows];
    }

    /**
     * 集計対象のサロン。削除済みも含めるのは、期間内に売上があるサロンを落とさないため。
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private static function merchants()
    {
        return Merchant::withTrashed()
            ->where('is_test', 0)
            ->orderBy('name')
            ->get(['id', 'name', 'deleted_at']);
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

    /**
     * created_at の年月ごとに件数を数える
     *
     * 絞り込みは素の日時比較で行う。DATE_FORMAT を WHERE 句に書くと
     * インデックスが効かないため、月への振り分けにだけ使う。
     *
     * @param mixed $query
     * @return array<string, int>
     */
    private static function countByMonth($query, Carbon $start, Carbon $end)
    {
        return $query->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as ym, COUNT(*) as cnt')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->groupBy('ym')
            ->pluck('cnt', 'ym')
            ->toArray();
    }
}
