<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A manually-added inbound email address the operator wants surfaced in
 * the outreach inbox even though it isn't tied to an outreach lead or a
 * CRM Customer/Contact. See ReplyDetectionService::detectCrmContacts —
 * watched emails are added to the same IMAP-search loop.
 */
class OutreachWatchedEmail extends Model
{
    protected $table = 'outreach_watched_emails';

    protected $fillable = [
        'email',
        'label',
        'last_scanned_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'last_scanned_at' => 'datetime',
    ];

    /**
     * Normalize email storage: always lowercased + trimmed. The unique
     * index on `email` then prevents accidental case-variant duplicates.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => strtolower(trim((string) $value)),
        );
    }
}
