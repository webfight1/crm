<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit-trail record for a single lead lifecycle event.
 *
 * Records are append-only. Do NOT call update() or delete() on these rows.
 */
class OutreachLeadEvent extends Model
{
    protected $table = 'outreach_lead_events';

    // No updated_at column — events are immutable
    const UPDATED_AT = null;

    // ── Event type constants ─────────────────────────────────────────────────
    const TYPE_ENROLLED           = 'enrolled';
    const TYPE_STEP_SENT          = 'step_sent';
    const TYPE_STEP_FAILED        = 'step_failed';
    const TYPE_STEP_SKIPPED       = 'step_skipped';
    const TYPE_REPLY_DETECTED     = 'reply_detected';
    const TYPE_BOUNCED            = 'bounced';
    const TYPE_STATUS_CHANGED     = 'status_changed';
    const TYPE_SEQUENCE_COMPLETED = 'sequence_completed';

    protected $fillable = [
        'lead_id',
        'event_type',
        'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────

    public function lead(): BelongsTo
    {
        return $this->belongsTo(OutreachLead::class, 'lead_id');
    }
}
