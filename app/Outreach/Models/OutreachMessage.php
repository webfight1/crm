<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One physical email message in an outreach conversation.
 *
 * Inbound rows are populated by ReplyDetectionService when a reply is matched
 * to one of our leads (via Message-ID header or sender-address heuristic).
 *
 * Outbound rows are reserved for CRM-originated replies (Layer 2 — not yet
 * implemented). Until then, sent campaign messages live in OutreachSendLog
 * and the inbox UI joins both sources at read-time.
 */
class OutreachMessage extends Model
{
    protected $table = 'outreach_messages';

    const DIRECTION_INBOUND  = 'inbound';
    const DIRECTION_OUTBOUND = 'outbound';

    protected $fillable = [
        'lead_id',
        'customer_id',
        'contact_id',
        'email_account_id',
        'direction',
        'message_id',
        'in_reply_to',
        'references_header',
        'from_email',
        'from_name',
        'subject',
        'body_text',
        'body_html',
        'has_attachments',
        'received_at',
        'read_at',
        'imap_uid',
    ];

    protected $casts = [
        'received_at'     => 'datetime',
        'read_at'         => 'datetime',
        'has_attachments' => 'boolean',
        'imap_uid'        => 'integer',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(OutreachLead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Contact::class, 'contact_id');
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(OutreachEmailAccount::class, 'email_account_id');
    }
}
