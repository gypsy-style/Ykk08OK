<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Agency extends Authenticatable
{
    use HasFactory;

    protected $fillable = [
        'agency_code',
        'name',
        'postal_code1',
        'postal_code2',
        'address',
        'phone',
        'contact_person',
        'email',
        'password'
    ];

    protected $hidden = [
        'password',
    ];

    public function merchants()
    {
        return $this->hasMany(Merchant::class);
    }

}
