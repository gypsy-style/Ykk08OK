<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_code', 'product_name', 'set_sale_name', 'category_id', 'product_image',
        'description', 'volume', 'price', 'wholesale_price',
        'retail_price','salon_price','salon_product_code', 'tax_rate', 'jan', 'lot', 'unit_quantity', 'status',
        'agent_sale_flag', 'single_sale_prohibited'
    ];

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
