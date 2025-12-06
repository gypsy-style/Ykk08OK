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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action'); // アクション名（例：order_created, status_updated）
            $table->string('model_type')->nullable(); // モデルの種類（例：Order, Product）
            $table->unsignedBigInteger('model_id')->nullable(); // モデルのID
            $table->json('old_values')->nullable(); // 変更前の値
            $table->json('new_values')->nullable(); // 変更後の値
            $table->text('description')->nullable(); // 詳細な説明
            $table->string('ip_address')->nullable(); // IPアドレス
            $table->string('user_agent')->nullable(); // ユーザーエージェント
            $table->timestamps();

            // インデックスの追加
            $table->index(['user_id', 'action']);
            $table->index(['model_type', 'model_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('activity_logs');
    }
}; 