<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminPermission
{
    // permission=2（倉庫）がアクセス可能なルート名
    private const WAREHOUSE_ALLOWED_ROUTES = [
        'admin.dashboard',
        'admin.orders.index',
        'admin.orders.show',
        'admin.orders.edit',
        'admin.orders.updateStatus',
        'admin.orders.updateShippingFee',
        'admin.orders.bulk-update',
    ];

    public function handle(Request $request, Closure $next)
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && $admin->permission === 2) {
            $routeName = $request->route()->getName();
            if (!in_array($routeName, self::WAREHOUSE_ALLOWED_ROUTES)) {
                abort(403, 'アクセス権限がありません。');
            }
        }

        return $next($request);
    }
}
