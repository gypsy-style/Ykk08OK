<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // `category` カラムを削除
            if (Schema::hasColumn('products', 'category')) {
                $table->dropColumn('category');
            }
            // `category_id` カラムを追加
            if (!Schema::hasColumn('products', 'category_id')) {
                $table->unsignedBigInteger('category_id')->after('product_name'); // 適切な位置に挿入
            }
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            // `category_id` を削除
            if (Schema::hasColumn('products', 'category_id')) {
                $table->dropColumn('category_id');
            }
            // `category` を復元
            if (!Schema::hasColumn('products', 'category')) {
                $table->string('category')->after('product_name'); // 適切な位置に復元
            }
        });
    }
};