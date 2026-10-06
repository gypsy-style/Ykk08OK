<?php

namespace Tests\Unit;

use App\Models\Product;
use Tests\TestCase;

// DB には触らない（モデルは保存せずメモリ上だけで組み立てる）
class ProductPriceForRankTest extends TestCase
{
    private function product(array $prices): Product
    {
        return new Product(array_merge(['price' => 1000], $prices));
    }

    /** @test */
    public function ランク1から5はそれぞれのランク価格を返す()
    {
        $product = $this->product([
            'price_1' => 900, 'price_2' => 800, 'price_3' => 700, 'price_4' => 600, 'price_5' => 500,
        ]);

        $this->assertSame(900, $product->getPriceForRank(1));
        $this->assertSame(800, $product->getPriceForRank(2));
        $this->assertSame(700, $product->getPriceForRank(3));
        $this->assertSame(600, $product->getPriceForRank(4));
        $this->assertSame(500, $product->getPriceForRank(5));
    }

    /** @test */
    public function ランク価格が未入力なら基本価格を返す()
    {
        $product = $this->product(['price_4' => null, 'price_5' => null]);

        $this->assertSame(1000, $product->getPriceForRank(4));
        $this->assertSame(1000, $product->getPriceForRank(5));
    }

    /** @test */
    public function ランク未設定はランク1として扱う()
    {
        $product = $this->product(['price_1' => 900]);

        $this->assertSame(900, $product->getPriceForRank(null));
    }

    /** @test */
    public function 範囲外のランクは基本価格を返す()
    {
        $product = $this->product(['price_5' => 500]);

        $this->assertSame(1000, $product->getPriceForRank(6));
        $this->assertSame(1000, $product->getPriceForRank(0));
    }
}
