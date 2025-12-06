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
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 代理店名
            $table->string('postal_code1'); // 郵便番号1
            $table->string('postal_code2'); // 郵便番号2
            $table->string('phone'); // 電話番号
            $table->string('contact_person'); // 担当者
            $table->string('email')->unique(); // メールアドレス
            $table->string('password'); // ログインパスワード（ハッシュ化）
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('agencies');
    }
};
