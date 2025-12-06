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
            $table->integer('salon_price')->nullable()->after('retail_price'); // 必要に応じて 'existing_column_name' を指定
            $table->string('salon_jan')->nullable()->after('salon_price'); // カラムの順序を調整可能
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
            $table->dropColumn(['salon_price', 'salon_jan']);
        });
    }
};
