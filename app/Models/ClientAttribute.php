<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAttribute extends Model
{
    protected $fillable = [
        'name',
        'label',
        'color',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];
}
