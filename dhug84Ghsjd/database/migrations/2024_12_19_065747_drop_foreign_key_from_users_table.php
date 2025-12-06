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
        Schema::table('users', function (Blueprint $table) {
            // 外部キー制約を削除
            $table->dropForeign(['merchant_id']);
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // 外部キー制約を復元
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
        });
    }
};
