<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductAccessory extends Model
{
    use HasFactory;

    protected $fillable = [
        'main_product_id',
        'accessory_product_id', 
        'quantity'
    ];

    public function mainProduct()
    {
        return $this->belongsTo(Product::class, 'main_product_id');
    }

    public function accessoryProduct()
    {
        return $this->belongsTo(Product::class, 'accessory_product_id');
    }
}