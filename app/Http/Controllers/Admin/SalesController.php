<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceLineSend;
use App\Models\Merchant;
use App\Models\MerchantPaymentConfirmation;
use App\Models\Order;
use App\Models\PaymentReminderSend;
use App\Models\Setting;
use App\Services\InvoiceLineSender;
use App\Services\InvoiceService;
use App\Services\PaymentReminderMessageService;
use App\Services\PaymentReminderSender;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    /** 売上集計対象のステータス（保留=4以外の確定注文） */
    private const SALES_STATUSES = [2, 3, 5, 6];

    public function index(
        Request $request,
        InvoiceService $invoiceService,
        PaymentReminderSender $reminderSender
    ) {
        $month = $request->query('month', Carbon::now()->format('Y-m'));

        // 商品別の月別売上集計
        $productSales = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->whereIn('o.status', self::SALES_STATUSES)
            ->whereNotIn('o.merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->whereRaw('DATE_FORMAT(o.created_at, "%Y-%m") = ?', [$month])
            ->groupBy('p.id', 'p.product_name')
            ->orderByDesc(DB::raw('SUM(od.quantity * od.price)'))
            ->select(
                'p.id as product_id',
                'p.product_name',
                DB::raw('SUM(od.quantity) as total_quantity'),
                DB::raw('SUM(od.quantity * od.price) as total_amount')
            )
            ->get();

        $headquartersProcessed = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price, SUM(shipping_fee) as shipping_fee')
            ->whereIn('status', self::SALES_STATUSES)
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->first();

        $shippingFeeCount = DB::table('orders')
            ->where('shipping_fee', '>', 0)
            ->whereIn('status', self::SALES_STATUSES)
            ->whereNotIn('merchant_id', function ($q) {
                $q->select('id')->from('merchants')->where('is_test', 1);
            })
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->count();

        // 月内に売上があった店舗一覧
        $merchantSales = DB::table('orders as o')
            ->join('merchants as m', 'm.id', '=', 'o.merchant_id')
            ->leftJoin('agencies as a', 'a.id', '=', 'm.agency_id')
            ->whereIn('o.status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(o.created_at, "%Y-%m") = ?', [$month])
            ->groupBy('m.id', 'm.name', 'm.member_rank', 'm.is_test', 'm.bank_account_name', 'a.name')
            ->orderByDesc(DB::raw('SUM(o.total_price + o.shipping_fee)'))
            ->select(
                'm.id as merchant_id',
                'm.name as merchant_name',
                'm.member_rank',
                'm.is_test as is_test',
                'm.bank_account_name',
                'a.name as agency_name',
                DB::raw('COUNT(o.id) as order_count'),
                DB::raw('SUM(o.total_price + o.shipping_fee) as total_amount')
            )
            ->get();

        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        $grandTotal = ((int) $productSales->sum('total_amount'))
            + ((int) ($headquartersProcessed->shipping_fee ?? 0));

        // 表示中の月の振込確認・送信履歴（加盟店IDで引けるようにする）
        $isFixedMonth = $invoiceService->isFixedMonth($month);
        $paymentConfirmations = MerchantPaymentConfirmation::where('month', $month)
            ->get()
            ->keyBy('merchant_id');
        $invoiceSends = InvoiceLineSend::where('month', $month)
            ->where('status', 'success')
            ->get()
            ->keyBy('merchant_id');

        // 未入金を上に集めたいので、振込確認済みの店舗を末尾へ回す（各グループ内は売上降順のまま）
        $merchantSales = $merchantSales
            ->sortBy(function ($m) use ($paymentConfirmations) {
                return isset($paymentConfirmations[$m->merchant_id]) ? 1 : 0;
            })
            ->values();

        // 督促ブロック用のデータ（確定月のみ組み立てる）
        $reminderSends = collect();
        $reminderTargets = [];
        $reminderMessage = '';
        $reminderPreview = '';
        $reminderPlaceholders = PaymentReminderMessageService::placeholders();

        if ($isFixedMonth) {
            $reminderSends = PaymentReminderSend::where('month', $month)
                ->where('status', 'success')
                ->get()
                ->keyBy('merchant_id');

            foreach ($merchantSales as $m) {
                if (isset($paymentConfirmations[$m->merchant_id])) {
                    continue;
                }
                $send = $reminderSends[$m->merchant_id] ?? null;
                $reminderTargets[] = [
                    'merchant_id' => (int) $m->merchant_id,
                    'merchant_name' => $m->merchant_name,
                    'reminded_at' => $send && $send->sent_at ? $send->sent_at->format('n/j') : null,
                ];
            }

            $reminderMessage = app(PaymentReminderMessageService::class)->template();

            // 先頭の対象加盟店の実データで、実際に送られる文面を作る
            if (!empty($reminderTargets)) {
                $first = Merchant::find($reminderTargets[0]['merchant_id']);
                if ($first) {
                    $reminderPreview = $reminderSender->buildBody(
                        $first,
                        $invoiceService->forMonth($first, $month)
                    );
                }
            }
        }

        return view('admin.sales.index', compact(
            'productSales',
            'merchantSales',
            'headquartersProcessed',
            'shippingFeeCount',
            'grandTotal',
            'month',
            'prevMonth',
            'nextMonth',
            'isFixedMonth',
            'paymentConfirmations',
            'invoiceSends',
            'reminderSends',
            'reminderTargets',
            'reminderMessage',
            'reminderPreview',
            'reminderPlaceholders'
        ));
    }

    public function show($merchantId, Request $request)
    {
        $merchant = Merchant::with('agency')->findOrFail($merchantId);

        $month = $request->query('month', Carbon::now()->format('Y-m'));
        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        // 月内のこの店舗の注文を全件取得
        $orders = Order::with('details.product')
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->orderBy('created_at', 'desc')
            ->get();

        // 月内全注文を商品ごとに合算
        $productAgg = [];
        $monthSubtotal = 0;
        $monthShippingFee = 0;
        foreach ($orders as $order) {
            $monthSubtotal += (int) ($order->total_price ?? 0);
            $monthShippingFee += (int) ($order->shipping_fee ?? 0);
            foreach ($order->details as $detail) {
                $pid = $detail->product_id;
                if (!isset($productAgg[$pid])) {
                    $productAgg[$pid] = [
                        'name' => optional($detail->product)->product_name ?? '',
                        'quantity' => 0,
                        'amount' => 0,
                    ];
                }
                $productAgg[$pid]['quantity'] += (int) $detail->quantity;
                $productAgg[$pid]['amount'] += (int) round($detail->price * $detail->quantity);
            }
        }
        uasort($productAgg, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        $monthTaxAmount = (int) round($monthSubtotal * 1.1) - $monthSubtotal;
        $monthGrandTotal = (int) round($monthSubtotal * 1.1) + $monthShippingFee;
        $monthTotalQuantity = array_sum(array_column($productAgg, 'quantity'));
        $monthOrderCount = $orders->count();

        // 合算コピー用テキスト
        $lines = [];
        $lines[] = $merchant->name ?? '';
        if ($merchant->postal_code1 && $merchant->postal_code2) {
            $lines[] = $merchant->postal_code1 . '-' . $merchant->postal_code2;
        }
        $lines[] = $merchant->address ?? '';
        $lines[] = $merchant->phone ?? '';
        $lines[] = '';
        // $lines[] = Carbon::parse($month . '-01')->format('Y年m月') . ' 受注合算（' . $monthOrderCount . '件）';
        foreach ($productAgg as $row) {
            $lines[] = $row['name'] . '×' . $row['quantity'] . '個 ' . number_format($row['amount']) . '円';
        }
        $lines[] = '送料 ' . number_format($monthShippingFee) . '円';
        $lines[] = '消費税（10%） ' . number_format($monthTaxAmount) . '円';
        $lines[] = '合計 ' . $monthTotalQuantity . '個 ' . number_format($monthGrandTotal) . '円';
        $copyText = implode("\n", $lines);

        return view('admin.sales.show', compact(
            'merchant',
            'orders',
            'productAgg',
            'monthSubtotal',
            'monthShippingFee',
            'monthTaxAmount',
            'monthGrandTotal',
            'monthTotalQuantity',
            'monthOrderCount',
            'copyText',
            'month',
            'prevMonth',
            'nextMonth'
        ));
    }

    public function invoice($merchantId, Request $request)
    {
        $merchant = Merchant::with('agency')->findOrFail($merchantId);

        $month = $request->query('month', Carbon::now()->format('Y-m'));

        $orders = Order::with('details.product')
            ->where('merchant_id', $merchant->id)
            ->whereIn('status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->get();

        $productAgg = [];
        $monthSubtotal = 0;
        $monthShippingFee = 0;
        foreach ($orders as $order) {
            $monthSubtotal += (int) ($order->total_price ?? 0);
            $monthShippingFee += (int) ($order->shipping_fee ?? 0);
            foreach ($order->details as $detail) {
                $pid = $detail->product_id;
                if (!isset($productAgg[$pid])) {
                    $productAgg[$pid] = [
                        'name' => optional($detail->product)->product_name ?? '',
                        'quantity' => 0,
                        'amount' => 0,
                        'unit_price' => (int) round($detail->price),
                    ];
                }
                $productAgg[$pid]['quantity'] += (int) $detail->quantity;
                $productAgg[$pid]['amount'] += (int) round($detail->price * $detail->quantity);
            }
        }
        uasort($productAgg, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });

        $monthTaxAmount = (int) round($monthSubtotal * 1.1) - $monthSubtotal;
        $monthGrandTotal = (int) round($monthSubtotal * 1.1) + $monthShippingFee;

        $invoiceDate = Carbon::parse($month . '-01')->addMonth();
        $invoiceNumber = $merchant->id . $invoiceDate->format('ymd');

        $companyName = Setting::getValue('company_name', '');
        $companyDetail = Setting::getValue('company_detail', '');
        $companySeal = Setting::getValue('company_seal', '');
        $companyBankInfo = Setting::getValue('company_bank_info', '');
        $companyPaymentNote = Setting::getValue('company_payment_note', '');

        return view('admin.sales.invoice', compact(
            'merchant',
            'productAgg',
            'monthSubtotal',
            'monthShippingFee',
            'monthTaxAmount',
            'monthGrandTotal',
            'invoiceDate',
            'invoiceNumber',
            'month',
            'companyName',
            'companyDetail',
            'companySeal',
            'companyBankInfo',
            'companyPaymentNote'
        ));
    }

    /**
     * 振込確認のON/OFFを切り替える
     *
     * 行があれば確認済み。もう一度押されたら行を消す。
     */
    public function togglePaymentConfirm($merchantId, Request $request, InvoiceService $invoiceService)
    {
        $merchant = Merchant::findOrFail($merchantId);
        $month = (string) $request->input('month');

        if (!$invoiceService->isFixedMonth($month)) {
            return response()->json(['message' => '当月の振込確認はできません。'], 400);
        }

        $existing = MerchantPaymentConfirmation::where('merchant_id', $merchant->id)
            ->where('month', $month)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['confirmed' => false, 'confirmed_at' => null]);
        }

        // 同時ダブルクリックで unique 制約違反が起きても 500 にしない
        $confirmation = MerchantPaymentConfirmation::firstOrCreate(
            ['merchant_id' => $merchant->id, 'month' => $month],
            [
                'admin_id' => Auth::guard('admin')->id(),
                'confirmed_at' => Carbon::now(),
            ]
        );

        return response()->json([
            'confirmed' => true,
            'confirmed_at' => $confirmation->confirmed_at->format('n/j'),
        ]);
    }

    /**
     * 請求書の案内をオーナーのLINEへ送信する
     *
     * 送信内容と履歴の扱いは月次バッチと同じ（InvoiceLineSender）。
     */
    public function sendInvoiceLine($merchantId, Request $request, InvoiceService $invoiceService, InvoiceLineSender $sender)
    {
        $merchant = Merchant::with('owner')->findOrFail($merchantId);
        $month = (string) $request->input('month');

        if (!$invoiceService->isFixedMonth($month)) {
            return response()->json(['success' => false, 'message' => '当月の請求書はまだ確定していません。'], 400);
        }

        $result = $sender->send($merchant, $month);

        return response()->json([
            'success' => $result['success'],
            'skipped' => $result['skipped'],
            'message' => $result['message'],
            'sent_at' => $result['sent_at'] ? $result['sent_at']->format('n/j') : null,
        ]);
    }

    /**
     * 振込督促をオーナーのLINEへ送信する
     *
     * 一斉送信もフロントから1件ずつこのエンドポイントを叩く。
     */
    public function sendPaymentReminder(
        $merchantId,
        Request $request,
        InvoiceService $invoiceService,
        PaymentReminderSender $sender
    ) {
        $merchant = Merchant::with('owner')->findOrFail($merchantId);
        $month = (string) $request->input('month');

        if (!$invoiceService->isFixedMonth($month)) {
            return response()->json(['success' => false, 'message' => '当月の督促は送信できません。'], 400);
        }

        $result = $sender->send($merchant, $month);

        return response()->json([
            'success' => $result['success'],
            'skipped' => $result['skipped'],
            'message' => $result['message'],
            'sent_at' => $result['sent_at'] ? $result['sent_at']->format('n/j') : null,
        ]);
    }

    /**
     * 督促の本文テンプレートを保存する
     */
    public function updateReminderMessage(Request $request)
    {
        $request->validate([
            'payment_reminder_message' => 'required|string|max:4000',
        ], [
            'payment_reminder_message.required' => '本文を入力してください。',
            'payment_reminder_message.max' => '本文は4000文字以内で入力してください。',
        ]);

        Setting::updateOrCreate(
            ['key' => PaymentReminderMessageService::KEY_MESSAGE],
            ['value' => $request->input('payment_reminder_message')]
        );

        return response()->json(['success' => true]);
    }
}
