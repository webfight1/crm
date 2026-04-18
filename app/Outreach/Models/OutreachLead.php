<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachLead extends Model
{
    protected $table = 'outreach_leads';

    // Status constants
    const STATUS_ACTIVE        = 'active';
    const STATUS_PAUSED        = 'paused';
    const STATUS_COMPLETED     = 'completed';
    const STATUS_BOUNCED       = 'bounced';
    const STATUS_UNSUBSCRIBED  = 'unsubscribed';

    // Qualification constants (lead vs skip)
    const QUALIFICATION_LEAD = 'lead';
    const QUALIFICATION_SKIP = 'skip';

    protected $fillable = [
        'campaign_id',
        'assigned_email_account_id',
        'first_name',
        'last_name',
        'email',
        'company',
        'website',
        'industry',
        'lcp_mobile',
        'performance_score',
        'notes',
        'qualification',
        'ai_line',
        'status',
        'current_step',
        'enrolled_at',
        'next_send_at',
        'last_sent_at',
        'replied',
        'replied_at',
        'processing_since',
    ];

    protected $casts = [
        'current_step'      => 'integer',
        'performance_score' => 'integer',
        'replied'           => 'boolean',
        'enrolled_at'       => 'datetime',
        'next_send_at'      => 'datetime',
        'last_sent_at'      => 'datetime',
        'replied_at'        => 'datetime',
        'processing_since'  => 'datetime',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'campaign_id');
    }

    public function assignedEmailAccount(): BelongsTo
    {
        return $this->belongsTo(OutreachEmailAccount::class, 'assigned_email_account_id');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(OutreachSendLog::class, 'lead_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function isReadyToSend(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ! $this->replied
            && $this->next_send_at !== null
            && $this->next_send_at->isPast();
    }

    public function markReplied(): void
    {
        $this->update([
            'replied'    => true,
            'replied_at' => now(),
            'status'     => self::STATUS_COMPLETED,
        ]);
    }

    public function markCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public function markBounced(): void
    {
        $this->update(['status' => self::STATUS_BOUNCED]);
    }

    /**
     * Record that the current step was sent and schedule the next one.
     *
     * Semantics of current_step:
     *   0          = nothing sent yet (lead just enrolled)
     *   N (N >= 1) = step_order N was the last step physically sent
     *
     * Therefore:
     *   - The step we just sent  = current_step + 1
     *   - The step to schedule   = current_step + 2
     *
     * Returns true when the next step was scheduled, false when the
     * sequence is complete (lead is marked completed).
     */
    public function advanceToNextStep(OutreachCampaign $campaign): bool
    {
        $justSentOrder = $this->current_step + 1; // step_order we just delivered
        $nextStepOrder = $this->current_step + 2; // step_order to send next
        $nextStep      = $campaign->getStepAt($nextStepOrder);

        if (! $nextStep) {
            // No more steps — record the final send and close the sequence
            $this->update([
                'current_step' => $justSentOrder,
                'last_sent_at' => now(),
                'status'       => self::STATUS_COMPLETED,
            ]);
            return false;
        }

        $this->update([
            'current_step' => $justSentOrder,
            'next_send_at' => $this->enrolled_at->copy()->addDays($nextStep->day_offset),
            'last_sent_at' => now(),
        ]);

        return true;
    }

    /**
     * Lock the lead for processing to prevent duplicate dispatch.
     */
    public function acquireProcessingLock(): void
    {
        $this->update(['processing_since' => now()]);
    }

    /**
     * Release the processing lock after send (success or failure).
     */
    public function releaseProcessingLock(): void
    {
        $this->update(['processing_since' => null]);
    }
}
