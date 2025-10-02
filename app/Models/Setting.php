<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'company_name',
        'registration_number',
        'vat_number',
        'address',
        'phone',
        'email',
        'website',
        'bank_name',
        'bank_account',
        'swift',
        'quotation_terms',
        'default_vat_rate',
        'logo_path'
    ];

    protected $casts = [
        'default_vat_rate' => 'decimal:2'
    ];

    public static function getSettings()
    {
        return static::first() ?? static::create([
            'company_name' => config('app.name'),
            'default_vat_rate' => 20.00
        ]);
    }
}
