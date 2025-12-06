<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run()
    {
        // カテゴリを作成
        $categories = [
            ['name' => 'シャンプー'],
            ['name' => 'トリートメント'],
            ['name' => 'スタイリング剤'],
            ['name' => 'カラー剤'],
            ['name' => 'パーマ剤'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }

        // 商品を作成
        $products = [
            [
                'product_code' => 'SH001',
                'product_name' => 'プレミアムシャンプー',
                'category_id' => 1,
                'description' => '髪に優しい高品質シャンプー',
                'volume' => '500ml',
                'price' => 3000,
                'wholesale_price' => 2500,
                'retail_price' => 3500,
                'salon_price' => 2800,
                'salon_product_code' => 'SP-SH001',
                'tax_rate' => 10,
                'jan' => '4901234567890',
                'lot' => 'LOT001',
                'unit_quantity' => 1,
                'status' => 'available',
                'agent_sale_flag' => 0,
            ],
            [
                'product_code' => 'TR001',
                'product_name' => 'ディープトリートメント',
                'category_id' => 2,
                'description' => '深く浸透するトリートメント',
                'volume' => '250ml',
                'price' => 4000,
                'wholesale_price' => 3200,
                'retail_price' => 4500,
                'salon_price' => 3600,
                'salon_product_code' => 'SP-TR001',
                'tax_rate' => 10,
                'jan' => '4901234567891',
                'lot' => 'LOT002',
                'unit_quantity' => 2,
                'status' => 'available',
                'agent_sale_flag' => 0,
            ],
            [
                'product_code' => 'ST001',
                'product_name' => 'ホールドワックス',
                'category_id' => 3,
                'description' => '長時間キープするワックス',
                'volume' => '100g',
                'price' => 2500,
                'wholesale_price' => 2000,
                'retail_price' => 3000,
                'salon_price' => 2200,
                'salon_product_code' => 'SP-ST001',
                'tax_rate' => 10,
                'jan' => '4901234567892',
                'lot' => 'LOT003',
                'unit_quantity' => 3,
                'status' => 'available',
                'agent_sale_flag' => 0,
            ],
            [
                'product_code' => 'CL001',
                'product_name' => 'ナチュラルカラー',
                'category_id' => 4,
                'description' => '自然な発色のカラー剤',
                'volume' => '80ml',
                'price' => 1500,
                'wholesale_price' => 1200,
                'retail_price' => 1800,
                'salon_price' => 1300,
                'salon_product_code' => 'SP-CL001',
                'tax_rate' => 10,
                'jan' => '4901234567893',
                'lot' => 'LOT004',
                'unit_quantity' => 1,
                'status' => 'available',
                'agent_sale_flag' => 0,
            ],
            [
                'product_code' => 'PR001',
                'product_name' => 'ソフトパーマ液',
                'category_id' => 5,
                'description' => '髪に優しいパーマ液',
                'volume' => '120ml',
                'price' => 2200,
                'wholesale_price' => 1800,
                'retail_price' => 2600,
                'salon_price' => 2000,
                'salon_product_code' => 'SP-PR001',
                'tax_rate' => 10,
                'jan' => '4901234567894',
                'lot' => 'LOT005',
                'unit_quantity' => 4,
                'status' => 'available',
                'agent_sale_flag' => 0,
            ],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}