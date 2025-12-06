<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantMember extends Model
{
    use HasFactory;
    protected $fillable = ['merchant_id', 'line_id', 'user_id'];

    /**
     * Merchant とのリレーション
     */
    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
