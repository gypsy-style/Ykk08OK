<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', Carbon::now()->format('Y-m'));
        $nowDate = date('Y年m月');
        $agencyId = auth('agencies')->user()->id;
        $todayOrders = DB::table('orders')
            ->whereDate('created_at', now()->toDateString())
            ->selectRaw('COUNT(*) as order_count, SUM(total_price) as total_price_sum')
            ->first();

        $headquartersProcessed = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price, SUM(shipping_fee) as shipping_fee')
            ->where('agency_id', $agencyId)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->first();

        // shipping_fee が 0以上の件数を取得
        $shippingFeeCount = DB::table('orders')
            ->where('shipping_fee', '>', 0)
            ->where('agency_id', $agencyId)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->count();

        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->subMonth()->format('Y-m');
        $nextMonth = $currentDate->addMonths(2)->format('Y-m');
        // ダッシュボード用のデータを取得する場合
        $data = [
            'merchantCount' => \App\Models\Merchant::where('agency_id', $agencyId)->count(),
            'todayOrderCount' => $todayOrders->order_count ?? 0, // 注文件数
            'todayTotalPriceSum' => $todayOrders->total_price_sum ?? 0, // 合計金額
        ];
        return view('agencies.dashboard', compact('data','headquartersProcessed', 'shippingFeeCount', 'month', 'prevMonth', 'nextMonth', 'nowDate'));
    }
}
