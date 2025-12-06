<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    // カテゴリーに関連する商品を取得
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    protected $fillable = ['name'];
}