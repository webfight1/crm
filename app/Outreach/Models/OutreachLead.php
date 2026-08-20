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
        'design_year',
        'design_age',
        'design_similarity',
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
        'design_year'       => 'integer',
        'design_age'        => 'integer',
        'design_similarity' => 'integer',
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

    public function messages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class, 'lead_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Company name with the Estonian legal form stripped, for greetings like
     * "Tere {{company_short}} tiim". Handles the form appearing at the start or
     * end of the name ("OÜ Bluebay", "VAS AKTIVA OÜ", "Osaühing Konsulent AT").
     *
     * Both abbreviations (OÜ, AS, MTÜ, FIE, SA, TÜ, UÜ) and full words
     * (Osaühing, Aktsiaselts, …) are matched case-insensitively as whole
     * tokens, so an "as"/"sa" hidden inside another word is never touched.
     * Falls back to the raw company name if stripping would leave it empty.
     */
    public function companyShort(): string
    {
        $name = trim((string) ($this->company ?? ''));
        if ($name === '') {
            return '';
        }

        $forms = [
            'osaühing', 'aktsiaselts', 'mittetulundusühing', 'sihtasutus',
            'tulundusühistu', 'usaldusühing', 'täisühing',
            'oü', 'mtü', 'fie', 'as', 'sa', 'tü', 'uü',
        ];

        $pattern = '/(?:^|\s)(?:' . implode('|', array_map(
            static fn (string $f): string => preg_quote($f, '/'),
            $forms
        )) . ')(?=\s|$)/iu';

        $clean = preg_replace($pattern, ' ', $name) ?? $name;
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? $clean, " \t\n\r,.-");

        return $clean !== '' ? $clean : $name;
    }

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
        $justSentStep  = $campaign->getStepAt($justSentOrder);
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

        // Gap between the step we just sent and the next one. day_offset is
        // "days since enrollment", so the inter-step gap is the difference.
        // Anchoring on now() instead of enrolled_at is critical: if the lead
        // sat in queue past its scheduled window (e.g. missing PageSpeed
        // data delayed step 1), anchoring on enrolled_at would back-date
        // every remaining next_send_at into the past and the entire
        // sequence would fire in a single cron burst.
        $gapDays = max(1, (int) $nextStep->day_offset - (int) ($justSentStep?->day_offset ?? 0));

        $this->update([
            'current_step' => $justSentOrder,
            'next_send_at' => now()->addDays($gapDays),
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
