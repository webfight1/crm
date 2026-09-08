<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Meriti võlgniku jooksva võlaepisoodi olek (mitmes meeldetuletus saadetud).
 */
class MeritDebtorState extends Model
{
    protected $fillable = [
        'merit_customer_id',
        'highest_level_sent',
        'last_sent_at',
        'debt_cleared_at',
        'handoff_notified_at',
    ];

    protected $casts = [
        'highest_level_sent'  => 'integer',
        'last_sent_at'        => 'datetime',
        'debt_cleared_at'     => 'datetime',
        'handoff_notified_at' => 'datetime',
    ];
}
