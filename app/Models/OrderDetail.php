<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'product_id', 'quantity', 'price'];

    // 注文とのリレーション
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // 商品とのリレーション
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}