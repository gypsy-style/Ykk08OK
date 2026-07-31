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
        Schema::create('merchant_payment_confirmations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');           // 加盟店ID
            $table->string('month', 7);                          // 対象月 YYYY-MM
            $table->unsignedBigInteger('admin_id')->nullable();  // 確認した管理者
            $table->timestamp('confirmed_at')->nullable();       // 確認日時
            $table->timestamps();

            // 同一加盟店・同一月の重複を防ぐ
            $table->unique(['merchant_id', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchant_payment_confirmations');
    }
};
