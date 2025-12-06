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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 店舗名
            $table->tinyInteger('status'); // ステータス (1, 2)
            $table->string('postal_code1', 3); // 郵便番号1
            $table->string('postal_code2', 4); // 郵便番号2
            $table->string('address'); // 住所
            $table->string('phone'); // 電話番号
            $table->string('contact_person'); // 担当者
            $table->unsignedBigInteger('user_id'); // ユーザーID
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade'); // 外部キー制約
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('merchants');
    }
};
