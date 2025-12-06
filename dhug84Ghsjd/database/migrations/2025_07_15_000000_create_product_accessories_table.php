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
        Schema::create('product_accessories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('main_product_id');
            $table->unsignedBigInteger('accessory_product_id');
            $table->integer('quantity');
            $table->timestamps();

            $table->foreign('main_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('accessory_product_id')->references('id')->on('products')->onDelete('cascade');
            $table->index(['main_product_id', 'accessory_product_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('product_accessories');
    }
};