<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * スタッフごとの権限
     *
     * 初期値で今の動き（注文と注文履歴だけできる）を保つ。
     * 既存のスタッフにも、これから追加するスタッフにも同じ値が入る。
     */
    public function up(): void
    {
        Schema::table('merchant_members', function (Blueprint $table) {
            $table->boolean('can_order')->default(true);
            $table->boolean('can_view_order_history')->default(true);
            $table->boolean('can_view_invoice')->default(false);
            $table->boolean('can_edit_merchant')->default(false);
            $table->boolean('can_manage_staff')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('merchant_members', function (Blueprint $table) {
            $table->dropColumn([
                'can_order',
                'can_view_order_history',
                'can_view_invoice',
                'can_edit_merchant',
                'can_manage_staff',
            ]);
        });
    }
};
