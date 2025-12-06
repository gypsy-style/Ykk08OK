<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'agency_id',
        'name',
        'merchant_code',
        'status',
        'postal_code1',
        'postal_code2',
        'address',
        'phone',
        'contact_person',
        'user_id',
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function members()
    {
        return $this->hasMany(MerchantMember::class);
    }

}
