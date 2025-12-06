<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->query('month', Carbon::now()->format('Y-m'));

        // ダッシュボード用のデータを取得する場合
        $data = [
            'agencyCount' => \App\Models\Agency::count(),
            'merchantCount' => \App\Models\Merchant::count(),
        ];

        // 各statusの件数を取得
        $statusCounts = DB::table('orders')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereIn('status', [2, 3, 4, 5, 6, 9]) // 対象とするステータス
            ->groupBy('status')
            ->pluck('count', 'status') // 結果を 'status' => 'count' の形式で取得
            ->toArray();
        // 全ステータスを初期化し、結果をマージして不足分を補完
        $statusCounts = array_replace([2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 9 => 0], $statusCounts);

        $headquartersProcessed = DB::table('orders')
            ->selectRaw('COUNT(id) as order_count, SUM(total_price) as total_price, SUM(shipping_fee) as shipping_fee')
            ->where('status', 2)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->first();

        // shipping_fee が 0以上の件数を取得
        $shippingFeeCount = DB::table('orders')
            ->where('shipping_fee', '>', 0)
            ->where('status', 2)
            ->whereRaw('DATE_FORMAT(created_at, "%Y-%m") = ?', [$month])
            ->count();


        $currentDate = Carbon::parse($month . '-01');
        $prevMonth = $currentDate->subMonth()->format('Y-m');
        $nextMonth = $currentDate->addMonths(2)->format('Y-m');

        return view('admin.dashboard', compact('data', 'headquartersProcessed', 'shippingFeeCount', 'statusCounts', 'month', 'prevMonth', 'nextMonth'));
    }
}
