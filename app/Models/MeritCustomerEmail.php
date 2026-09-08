<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Käsitsi lisatud e-post Meriti kliendile (kui Meritis puudub).
 */
class MeritCustomerEmail extends Model
{
    protected $fillable = [
        'merit_customer_id',
        'customer_name',
        'email',
    ];
}
