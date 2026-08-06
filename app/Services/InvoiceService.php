<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\Order;
use Carbon\Carbon;

/**
 * 請求書の集計ロジック
 *
 * LIFFの請求書一覧・請求書PDF・月次のLINE送信バッチで共用する。
 * 税率や送料の扱いを変えるときは必ずここだけを直すこと。
 */
class InvoiceService
{
    /** 旧ルールでの売上集計対象ステータス（保留=4以外の確定注文） */
    public const SALES_STATUSES = [2, 3, 5, 6];

    /** 発送済み */
    public const SHIPPED_STATUS = 6;

    /**
     * 「発送済みのみを請求する」新ルールの適用開始日
     *
     * この日以降に作成された注文が新ルールの対象。請求月ではなく注文日で切ることで、
     * 1件の注文が旧ルールと新ルールの両方に計上される二重請求を防いでいる。
     */
    public const SHIPPED_ONLY_FROM = '2026-09-01';

    /**
     * 請求対象の注文に絞り込む
     *
     * 旧ルール（カットオフ前の注文）: 確定注文すべて
     * 新ルール（カットオフ以降の注文）: 発送済みのみ
     *
     * @param mixed $query Eloquent または DB::table のクエリビルダ
     * @param string $alias テーブル別名（DB::table の join で 'o' などを使っている場合に渡す）
     * @return mixed
     */
    public static function applyInvoiceScope($query, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return $query->where(function ($outer) use ($p) {
            $outer->where(function ($old) use ($p) {
                $old->where($p . 'created_at', '<', self::SHIPPED_ONLY_FROM)
                    ->whereIn($p . 'status', self::SALES_STATUSES);
            })->orWhere(function ($new) use ($p) {
                $new->where($p . 'created_at', '>=', self::SHIPPED_ONLY_FROM)
                    ->where($p . 'status', self::SHIPPED_STATUS)
                    ->whereNotNull($p . 'shipped_at');
            });
        });
    }

    /**
     * 請求上の計上日を表す SQL 式を返す
     *
     * 旧ルールは注文日、新ルールは発送日。埋め込む値はクラス定数のみでユーザー入力を含まない。
     *
     * @param string $alias テーブル別名
     * @return string
     */
    private static function billingDateSql($alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return 'IF(' . $p . 'created_at < "' . self::SHIPPED_ONLY_FROM . '", ' . $p . 'created_at, ' . $p . 'shipped_at)';
    }

    /**
     * 請求上の計上月で絞り込む
     *
     * @param mixed $query
     * @param string $month YYYY-MM
     * @param string $alias テーブル別名
     * @return mixed
     */
    public static function applyInvoiceMonth($query, $month, $alias = '')
    {
        return $query->whereRaw('DATE_FORMAT(' . self::billingDateSql($alias) . ', "%Y-%m") = ?', [$month]);
    }

    /**
     * 請求上の計上日が確定月（前月以前）に入っているものだけに絞り込む
     *
     * @param mixed $query
     * @param string $alias テーブル別名
     * @return mixed
     */
    public static function applyFixedMonths($query, $alias = '')
    {
        return $query->whereRaw(
            self::billingDateSql($alias) . ' < ?',
            [Carbon::now()->startOfMonth()->format('Y-m-d H:i:s')]
        );
    }

    /**
     * 注文の請求上の計上日を返す（旧ルール=注文日、新ルール=発送日）
     *
     * @param Order $order
     * @return \Carbon\Carbon|null
     */
    public static function billingDate(Order $order)
    {
        if ($order->created_at < Carbon::parse(self::SHIPPED_ONLY_FROM)) {
            return $order->created_at;
        }

        return $order->shipped_at;
    }

    /**
     * 確定済み（前月まで）の月別集計を返す
     *
     * @param Merchant $merchant
     * @return array ['YYYY-MM' => 集計配列, ...] 新しい月が先
     */
    public function monthlyBreakdown(Merchant $merchant)
    {
        // 当月は未確定のため前月までを対象とする
        $query = Order::with('details.product')
            ->where('merchant_id', $merchant->id);
        self::applyInvoiceScope($query);
        self::applyFixedMonths($query);
        $orders = $query->orderBy('created_at', 'desc')->get();

        $grouped = [];
        foreach ($orders as $order) {
            $billingDate = self::billingDate($order);
            if (!$billingDate) {
                continue;
            }
            $grouped[$billingDate->format('Y-m')][] = $order;
        }

        krsort($grouped);

        $result = [];
        foreach ($grouped as $month => $monthOrders) {
            $result[$month] = $this->aggregate($monthOrders, $merchant, $month);
        }

        return $result;
    }

    /**
     * 確定済み（前月まで）の請求書が1件でもあるか
     *
     * @param Merchant $merchant
     * @return bool
     */
    public function hasInvoice(Merchant $merchant)
    {
        $query = Order::where('merchant_id', $merchant->id);
        self::applyInvoiceScope($query);
        self::applyFixedMonths($query);

        return $query->exists();
    }

    /**
     * 指定月の集計を返す（注文が無い場合も 0 埋めの配列を返す）
     *
     * @param Merchant $merchant
     * @param string $month YYYY-MM
     * @return array
     */
    public function forMonth(Merchant $merchant, $month)
    {
        $query = Order::with('details.product')
            ->where('merchant_id', $merchant->id);
        self::applyInvoiceScope($query);
        self::applyInvoiceMonth($query, $month);
        $orders = $query->get();

        return $this->aggregate($orders, $merchant, $month);
    }

    /**
     * 指定月が請求書として確定済みか（当月以降は未確定）
     *
     * @param string $month YYYY-MM
     * @return bool
     */
    public function isFixedMonth($month)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            return false;
        }
        return $month < Carbon::now()->format('Y-m');
    }

    /**
     * 請求書一覧（LIFF）のURLを返す
     *
     * @return string
     */
    public function invoiceUrl()
    {
        $liffId = config('app.invoice_liff_id') ?: config('app.merchant_information_liff_id');
        return $liffId ? 'https://liff.line.me/' . $liffId : '';
    }

    /**
     * 注文の集合を1ヶ月分の請求内容に集計する
     *
     * @param iterable $orders
     * @param Merchant $merchant
     * @param string $month YYYY-MM
     * @return array
     */
    private function aggregate($orders, Merchant $merchant, $month)
    {
        $subtotal = 0;
        $shippingFee = 0;
        $orderCount = 0;
        $products = [];

        foreach ($orders as $order) {
            $subtotal += (int) ($order->total_price ?? 0);
            $shippingFee += (int) ($order->shipping_fee ?? 0);
            $orderCount++;

            foreach ($order->details as $detail) {
                $pid = $detail->product_id;
                if (!isset($products[$pid])) {
                    $products[$pid] = [
                        'name' => optional($detail->product)->product_name ?? '',
                        'quantity' => 0,
                        'amount' => 0,
                        'unit_price' => (int) round($detail->price),
                    ];
                }
                $products[$pid]['quantity'] += (int) $detail->quantity;
                $products[$pid]['amount'] += (int) round($detail->price * $detail->quantity);
            }
        }

        uasort($products, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        $invoiceDate = Carbon::parse($month . '-01')->addMonth();

        return [
            'month' => $month,
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'order_count' => $orderCount,
            'products' => $products,
            'tax' => (int) round($subtotal * 1.1) - $subtotal,
            'grand_total' => (int) round($subtotal * 1.1) + $shippingFee,
            'invoice_date' => $invoiceDate,
            'invoice_number' => $merchant->id . $invoiceDate->format('ymd'),
            'label' => Carbon::parse($month . '-01')->format('Y年n月分'),
        ];
    }
}
