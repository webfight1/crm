<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ühe arve meeldetuletuste olek (mitmes aste saadetud, kas Marius teavitatud).
 */
class MeritInvoiceState extends Model
{
    protected $fillable = [
        'invoice_key',
        'merit_customer_id',
        'invoice_no',
        'highest_step_sent',
        'last_sent_at',
        'marius_notified_at',
        'resolved_at',
        'suppressed_at',
    ];

    protected $casts = [
        'highest_step_sent'  => 'integer',
        'last_sent_at'       => 'datetime',
        'marius_notified_at' => 'datetime',
        'resolved_at'        => 'datetime',
        'suppressed_at'      => 'datetime',
    ];
}
