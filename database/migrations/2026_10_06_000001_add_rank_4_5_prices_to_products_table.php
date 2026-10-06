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
            // 会員ランク4・5の価格（未設定なら従来のpriceを使用するためNULL許可）
            $table->integer('price_4')->nullable()->after('show_price_3');
            $table->boolean('show_price_4')->default(false)->after('price_4');
            $table->integer('price_5')->nullable()->after('show_price_4');
            $table->boolean('show_price_5')->default(false)->after('price_5');
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
            $table->dropColumn(['price_4', 'show_price_4', 'price_5', 'show_price_5']);
        });
    }
};
