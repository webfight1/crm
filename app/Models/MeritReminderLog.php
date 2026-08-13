<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Meriti meeldetuletuse auditkirje — üks saadetud/ebaõnnestunud kiri.
 */
class MeritReminderLog extends Model
{
    protected $fillable = [
        'merit_customer_id',
        'customer_name',
        'invoice_no',
        'email',
        'level',
        'overdue_days',
        'total_unpaid',
        'invoice_numbers',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'level'           => 'integer',
        'overdue_days'    => 'integer',
        'total_unpaid'    => 'decimal:2',
        'invoice_numbers' => 'array',
        'sent_at'         => 'datetime',
    ];
}
