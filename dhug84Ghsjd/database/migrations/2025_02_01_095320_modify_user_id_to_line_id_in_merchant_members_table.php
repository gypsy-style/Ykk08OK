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
    public function up(): void
    {
        Schema::table('merchant_members', function (Blueprint $table) {
            $table->dropColumn('user_id'); // user_id を削除
            $table->string('line_id')->after('merchant_id'); // line_id を追加
        });
    }

    public function down(): void
    {
        Schema::table('merchant_members', function (Blueprint $table) {
            $table->dropColumn('line_id'); // line_id を削除
            $table->unsignedBigInteger('user_id')->after('merchant_id'); // user_id を復元
        });
    }
};
