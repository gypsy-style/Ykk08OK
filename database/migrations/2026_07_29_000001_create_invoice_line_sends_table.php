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
        Schema::create('invoice_line_sends', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id'); // 加盟店ID
            $table->string('month', 7);                // 請求対象月 YYYY-MM
            $table->string('line_id')->nullable();     // 送信先のLINEユーザーID
            $table->string('status', 20);              // success / failed
            $table->text('error')->nullable();         // 失敗理由
            $table->timestamp('sent_at')->nullable();  // 送信成功日時
            $table->timestamps();

            // 同一加盟店・同一月の二重送信を防ぐ
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
        Schema::dropIfExists('invoice_line_sends');
    }
};
