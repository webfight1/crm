<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachScheduledReply extends Model
{
    protected $table = 'outreach_scheduled_replies';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_SENT      = 'sent';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'email_lower',
        'to_email',
        'to_name',
        'subject',
        'body',
        'in_reply_to',
        'references_header',
        'account_id',
        'scheduled_at',
        'status',
        'sent_at',
        'error_message',
        'created_by_user_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(OutreachEmailAccount::class, 'account_id');
    }
}
