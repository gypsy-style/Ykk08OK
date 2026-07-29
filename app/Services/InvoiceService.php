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
    /** 売上集計対象のステータス（保留=4以外の確定注文） */
    public const SALES_STATUSES = [2, 3, 5, 6];

    /**
     * 確定済み（前月まで）の月別集計を返す
     *
     * @param Merchant $merchant
     * @return array ['YYYY-MM' => 集計配列, ...] 新しい月が先
     */
    public function monthlyBreakdown(Merchant $merchant)
    {
        // 当月は未確定のため前月までを対象とする
        $orders = Order::with('details.product')
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', self::SALES_STATUSES)
            ->where('created_at', '<', Carbon::now()->startOfMonth())
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = [];
        foreach ($orders as $order) {
            $month = $order->created_at->format('Y-m');
            $grouped[$month][] = $order;
        }

        $result = [];
        foreach ($grouped as $month => $monthOrders) {
            $result[$month] = $this->aggregate($monthOrders, $merchant, $month);
        }

        return $result;
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
        $orders = Order::with('details.product')
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->get();

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
