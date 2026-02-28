<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Merchant extends Model
{
    use HasFactory;

    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'agency_id',
        'name',
        'merchant_code',
        'campaign_code',
        'status',
        'member_rank',
        'postal_code1',
        'postal_code2',
        'address',
        'phone',
        'contact_person',
        'user_id',
    ];

    protected $casts = [
        'member_rank' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function (Merchant $merchant) {
            if (empty($merchant->merchant_code)) {
                $merchant->merchant_code = self::generateUniqueMerchantCode();
            }
        });
    }

    public static function generateUniqueMerchantCode(): string
    {
        do {
            $code = 'GOON-' . Str::upper(Str::random(4));
        } while (DB::table('merchants')->where('merchant_code', $code)->exists());

        return $code;
    }

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
