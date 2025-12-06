<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 外部キーが存在する場合のみ安全に削除
        if (Schema::hasColumn('orders', 'user_id')) {
            try {
                Schema::table('orders', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (\Throwable $e) {
                // 何もしない（外部キーが既に無い場合など）
            }
        }

        if (Schema::hasColumn('orders', 'merchant_id')) {
            try {
                Schema::table('orders', function (Blueprint $table) {
                    $table->dropForeign(['merchant_id']);
                });
            } catch (\Throwable $e) {
                // 何もしない
            }
        }
    }

    public function down()
    {
        // 必要に応じて元の外部キーを再作成（カラムが存在する場合のみ）
        if (Schema::hasColumn('orders', 'user_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }
};
