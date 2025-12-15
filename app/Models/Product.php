<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_code', 'product_name', 'set_sale_name', 'category_id', 'product_image',
        'description', 'volume', 'price', 'price_1', 'price_2', 'price_3', 'wholesale_price',
        'retail_price','salon_price','salon_product_code', 'tax_rate', 'jan', 'lot', 'unit_quantity', 'status',
        'agent_sale_flag', 'single_sale_prohibited'
    ];

    protected $casts = [
        'price' => 'integer',
        'price_1' => 'integer',
        'price_2' => 'integer',
        'price_3' => 'integer',
        'tax_rate' => 'integer',
    ];

    public function getPriceForRank(?int $memberRank): int
    {
        $rank = $memberRank ?? 1;
        if ($rank === 1 && $this->price_1 !== null) return (int) $this->price_1;
        if ($rank === 2 && $this->price_2 !== null) return (int) $this->price_2;
        if ($rank === 3 && $this->price_3 !== null) return (int) $this->price_3;
        return (int) $this->price;
    }

    /**
     * Category モデルとのリレーション
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id'); // 外部キーが category_id
    }

    /**
     * ProductAccessory モデルとのリレーション（付属商品）
     */
    public function accessories()
    {
        return $this->hasMany(ProductAccessory::class, 'main_product_id');
    }
}
