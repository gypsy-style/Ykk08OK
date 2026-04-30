<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    /** 売上集計対象のステータス（保留=4以外の確定注文） */
    private const SALES_STATUSES = [2, 3, 5, 6];

    public function index(Request $request)
    {
        $month = $request->query('month', Carbon::now()->format('Y-m'));

        // 商品別の月別売上集計
        $productSales = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->whereIn('o.status', self::SALES_STATUSES)
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
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->first();

        $shippingFeeCount = DB::table('orders')
            ->where('shipping_fee', '>', 0)
            ->whereIn('status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->count();

        // 月内に売上があった店舗一覧
        $merchantSales = DB::table('orders as o')
            ->join('merchants as m', 'm.id', '=', 'o.merchant_id')
            ->leftJoin('agencies as a', 'a.id', '=', 'm.agency_id')
            ->whereIn('o.status', self::SALES_STATUSES)
            ->whereRaw('DATE_FORMAT(o.created_at, "%Y-%m") = ?', [$month])
            ->groupBy('m.id', 'm.name', 'm.member_rank', 'a.name')
            ->orderByDesc(DB::raw('SUM(o.total_price + o.shipping_fee)'))
            ->select(
                'm.id as merchant_id',
                'm.name as merchant_name',
                'm.member_rank',
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

        return view('admin.sales.index', compact(
            'productSales',
            'merchantSales',
            'headquartersProcessed',
            'shippingFeeCount',
            'grandTotal',
            'month',
            'prevMonth',
            'nextMonth'
        ));
    }
}
