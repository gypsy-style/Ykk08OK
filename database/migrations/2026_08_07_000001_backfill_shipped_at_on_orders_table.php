<?php

use App\Services\InvoiceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * カットオフ以降に作成された発送済み注文の発送日を、アクティビティログから復元する。
 *
 * shipped_at は 2026-08 のリリースで追加した列のため、それ以前に発送された注文は空のままになる。
 * 新ルールは shipped_at が入っていない注文を請求から除外するので、埋めないと請求額が落ちる。
 */
return new class extends Migration
{
    public function up()
    {
        // 最後に発送済みへ変わった時刻を採用する。コントローラ側の書き込みと揃えるため
        $lastShippedAt = "(select max(a.created_at) from activity_logs a"
            . " where a.model_id = orders.id and a.model_type = 'App\\\\Models\\\\Order'"
            . " and a.new_status = " . InvoiceService::SHIPPED_STATUS . ")";

        $this->targetOrders()->update(['shipped_at' => DB::raw($lastShippedAt)]);

        // ログが残っていない注文は、最終更新時刻を発送日とみなす
        $this->targetOrders()->update(['shipped_at' => DB::raw('updated_at')]);
    }

    public function down()
    {
        DB::table('orders')
            ->where('created_at', '>=', InvoiceService::SHIPPED_ONLY_FROM)
            ->where('status', InvoiceService::SHIPPED_STATUS)
            ->update(['shipped_at' => null]);
    }

    private function targetOrders()
    {
        return DB::table('orders')
            ->where('created_at', '>=', InvoiceService::SHIPPED_ONLY_FROM)
            ->where('status', InvoiceService::SHIPPED_STATUS)
            ->whereNull('shipped_at');
    }
};
