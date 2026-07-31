<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantPaymentConfirmation extends Model
{
    protected $fillable = [
        'merchant_id',
        'month',
        'admin_id',
        'confirmed_at',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
