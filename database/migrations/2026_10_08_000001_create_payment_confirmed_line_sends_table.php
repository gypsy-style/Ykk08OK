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
        Schema::create('payment_confirmed_line_sends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id'); // 加盟店ID
            $table->string('month', 7);                // 請求対象月 YYYY-MM
            $table->string('line_id')->nullable();     // 送信先のLINEユーザーID
            $table->string('status', 20);              // success / failed / skipped
            $table->text('error')->nullable();         // 失敗・スキップの理由
            $table->timestamp('sent_at')->nullable();  // 送信成功日時
            $table->timestamps();

            // 1加盟店・1ヶ月につき1件（成功済みなら再送しない）
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
        Schema::dropIfExists('payment_confirmed_line_sends');
    }
};
