<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_code', 'product_name', 'set_sale_name', 'category_id', 'product_image',
        'description', 'volume', 'price', 'price_1', 'price_2', 'price_3', 'price_4', 'price_5', 'wholesale_price',
        'retail_price', 'tax_rate', 'jan', 'lot', 'unit_quantity', 'status',
        'agent_sale_flag', 'single_sale_prohibited',
        'show_price_1', 'show_price_2', 'show_price_3', 'show_price_4', 'show_price_5',
    ];

    protected $casts = [
        'price' => 'integer',
        'price_1' => 'integer',
        'price_2' => 'integer',
        'price_3' => 'integer',
        'price_4' => 'integer',
        'price_5' => 'integer',
        'tax_rate' => 'integer',
        'show_price_1' => 'boolean',
        'show_price_2' => 'boolean',
        'show_price_3' => 'boolean',
        'show_price_4' => 'boolean',
        'show_price_5' => 'boolean',
    ];

    public function getPriceForRank(?int $memberRank): int
    {
        $rank = $memberRank ?? 1;
        if ($rank === 1 && $this->price_1 !== null) return (int) $this->price_1;
        if ($rank === 2 && $this->price_2 !== null) return (int) $this->price_2;
        if ($rank === 3 && $this->price_3 !== null) return (int) $this->price_3;
        if ($rank === 4 && $this->price_4 !== null) return (int) $this->price_4;
        if ($rank === 5 && $this->price_5 !== null) return (int) $this->price_5;
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
