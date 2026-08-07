<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 発送済み注文すべての発送日を、アクティビティログから復元する。
 *
 * 請求のカットオフ（2026-07-01 以降の注文のみ新ルール）を撤廃し、全期間を発送日基準にしたため、
 * それ以前に発送された注文にも shipped_at が要る。埋めないと過去の請求書が空になる。
 */
return new class extends Migration
{
    public function up()
    {
        // 最後に発送済みへ変わった時刻を採用する。コントローラ側の書き込みと揃えるため
        $lastShippedAt = "(select max(a.created_at) from activity_logs a"
            . " where a.model_id = orders.id and a.model_type = 'App\\\\Models\\\\Order'"
            . " and a.new_status = 6)";

        $this->targetOrders()->update(['shipped_at' => DB::raw($lastShippedAt)]);

        // ログが残っていない注文は、最終更新時刻を発送日とみなす
        $this->targetOrders()->update(['shipped_at' => DB::raw('updated_at')]);
    }

    public function down()
    {
        DB::table('orders')
            ->where('created_at', '<', '2026-07-01')
            ->where('status', 6)
            ->update(['shipped_at' => null]);
    }

    private function targetOrders()
    {
        return DB::table('orders')
            ->where('status', 6)
            ->whereNull('shipped_at');
    }
};
