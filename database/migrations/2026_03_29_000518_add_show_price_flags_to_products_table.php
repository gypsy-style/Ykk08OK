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
            $table->boolean('show_price_1')->default(false)->after('price_1');
            $table->boolean('show_price_2')->default(false)->after('price_2');
            $table->boolean('show_price_3')->default(false)->after('price_3');
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['show_price_1', 'show_price_2', 'show_price_3']);
        });
    }
};
