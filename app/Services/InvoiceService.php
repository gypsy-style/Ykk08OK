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
    /** 売上分析で対象とするステータス（保留=4以外の確定注文）。請求の集計には使わない */
    public const SALES_STATUSES = [2, 3, 5, 6];

    /** 発送済み */
    public const SHIPPED_STATUS = 6;

    /**
     * 請求対象の注文に絞り込む
     *
     * 発送済みのものだけを請求する。発送日が入っていない注文は計上月を決められないため除外する。
     *
     * @param mixed $query Eloquent または DB::table のクエリビルダ
     * @param string $alias テーブル別名（DB::table の join で 'o' などを使っている場合に渡す）
     * @return mixed
     */
    public static function applyInvoiceScope($query, $alias = '')
    {
        $p = $alias === '' ? '' : $alias . '.';

        return $query->where($p . 'status', self::SHIPPED_STATUS)
            ->whereNotNull($p . 'shipped_at');
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
        $p = $alias === '' ? '' : $alias . '.';

        return $query->whereRaw('DATE_FORMAT(' . $p . 'shipped_at, "%Y-%m") = ?', [$month]);
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
        $p = $alias === '' ? '' : $alias . '.';

        return $query->where(
            $p . 'shipped_at',
            '<',
            Carbon::now()->startOfMonth()->format('Y-m-d H:i:s')
        );
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
        $orders = $query->orderBy('shipped_at', 'desc')->get();

        $grouped = [];
        foreach ($orders as $order) {
            $grouped[$order->shipped_at->format('Y-m')][] = $order;
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
