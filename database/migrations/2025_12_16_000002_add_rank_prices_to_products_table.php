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
        Schema::table('products', function (Blueprint $table) {
            // 会員ランク別の価格（未設定なら従来のpriceを使用するためNULL許可）
            if (!Schema::hasColumn('products', 'price_1')) {
                $table->integer('price_1')->nullable()->after('price');
            }
            if (!Schema::hasColumn('products', 'price_2')) {
                $table->integer('price_2')->nullable()->after('price_1');
            }
            if (!Schema::hasColumn('products', 'price_3')) {
                $table->integer('price_3')->nullable()->after('price_2');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['price_1', 'price_2', 'price_3']);
        });
    }
};


