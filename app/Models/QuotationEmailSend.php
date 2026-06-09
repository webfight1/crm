<?php

namespace App\Models;

use App\Outreach\Models\OutreachEmailAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationEmailSend extends Model
{
    protected $fillable = [
        'quotation_id',
        'sender_account_id',
        'to_email',
        'subject',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function senderAccount(): BelongsTo
    {
        return $this->belongsTo(OutreachEmailAccount::class, 'sender_account_id');
    }
}
