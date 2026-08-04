<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentReminderSend extends Model
{
    protected $fillable = [
        'merchant_id',
        'month',
        'line_id',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function merchant()
    {
        return $this->belongsTo(Merchant::class);
    }
}
