<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', Carbon::now()->format('Y-m'));

        // 商品別の月別売上集計（status=2: 本部処理済み）
        $productSales = DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('products as p', 'p.id', '=', 'od.product_id')
            ->where('o.status', 2)
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
            ->where('status', 2)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->first();

        $shippingFeeCount = DB::table('orders')
            ->where('shipping_fee', '>', 0)
            ->where('status', 2)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->count();

        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');

        $grandTotal = ((int) $productSales->sum('total_amount'))
            + ((int) ($headquartersProcessed->shipping_fee ?? 0));

        return view('admin.sales.index', compact(
            'productSales',
            'headquartersProcessed',
            'shippingFeeCount',
            'grandTotal',
            'month',
            'prevMonth',
            'nextMonth'
        ));
    }
}
