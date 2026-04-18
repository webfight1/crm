<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachCampaignStep extends Model
{
    protected $table = 'outreach_campaign_steps';

    protected $fillable = [
        'campaign_id',
        'step_order',
        'day_offset',
        'subject',
        'body_template',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'day_offset' => 'integer',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'campaign_id');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(OutreachSendLog::class, 'campaign_step_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * Replace template variables with lead data.
     *
     * Supported placeholders:
     *   {{first_name}}, {{last_name}}, {{full_name}},
     *   {{company}}, {{website}}, {{industry}}, {{email}},
     *   {{ai_line}}, {{lcp}}, {{performance_score}}
     *
     * {{ai_line}} is populated by OutreachEmailService before rendering:
     *   - campaign.use_ai_line = true  → lead.ai_line (generated once, saved)
     *   - campaign.use_ai_line = false → empty string
     */
    public function renderSubject(OutreachLead $lead): string
    {
        return $this->replaceVariables($this->subject, $lead);
    }

    public function renderBody(OutreachLead $lead): string
    {
        return $this->replaceVariables($this->body_template, $lead);
    }

    private function replaceVariables(string $template, OutreachLead $lead): string
    {
        $variables = [
            '{{first_name}}'        => $lead->first_name,
            '{{last_name}}'         => $lead->last_name ?? '',
            '{{full_name}}'         => trim("{$lead->first_name} " . ($lead->last_name ?? '')),
            '{{company}}'           => $lead->company ?? '',
            '{{website}}'           => $lead->website ?? '',
            '{{industry}}'          => $lead->industry ?? '',
            '{{email}}'             => $lead->email,
            '{{lcp}}'               => $lead->lcp_mobile ?? '',
            '{{performance_score}}' => $lead->performance_score !== null
                                        ? (string) $lead->performance_score
                                        : '',
            // ai_line is written to lead.ai_line by OutreachEmailService
            // before render is called, so reading it here is always safe.
            '{{ai_line}}'           => $lead->ai_line ?? '',
        ];

        // strtr() is used instead of str_replace() because it performs all
        // substitutions in a single pass with no risk of one replacement
        // containing a placeholder that gets substituted again.
        return strtr($template, $variables);
    }
}
