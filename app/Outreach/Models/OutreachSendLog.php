<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachSendLog extends Model
{
    protected $table = 'outreach_send_logs';

    // Status constants
    const STATUS_PENDING  = 'pending';
    const STATUS_SENT     = 'sent';
    const STATUS_FAILED   = 'failed';
    const STATUS_SKIPPED  = 'skipped';

    protected $fillable = [
        'lead_id',
        'campaign_id',
        'email_account_id',
        'campaign_step_id',
        'step_order',
        'to_email',
        'from_email',
        'subject',
        'body',
        'status',
        'error_message',
        'message_id',
        'idempotency_key',
        'sent_at',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'sent_at'    => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────

    public function lead(): BelongsTo
    {
        return $this->belongsTo(OutreachLead::class, 'lead_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'campaign_id');
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(OutreachEmailAccount::class, 'email_account_id');
    }

    public function campaignStep(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaignStep::class, 'campaign_step_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function markSent(string $messageId): void
    {
        $this->update([
            'status'     => self::STATUS_SENT,
            'message_id' => $messageId,
            'sent_at'    => now(),
        ]);
    }

    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status'          => self::STATUS_FAILED,
            'error_message'   => $errorMessage,
            // Null out the key so the next retry attempt can create a fresh
            // pending record with the same idempotency_key without hitting the
            // unique constraint. Failed records are inert — they don't block resends.
            'idempotency_key' => null,
        ]);
    }
}
