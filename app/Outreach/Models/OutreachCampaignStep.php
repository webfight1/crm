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
     * Supported: {{first_name}}, {{last_name}}, {{full_name}}, {{company}}, {{website}}, {{email}}
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
            '{{first_name}}' => $lead->first_name,
            '{{last_name}}'  => $lead->last_name ?? '',
            '{{full_name}}'  => trim("{$lead->first_name} " . ($lead->last_name ?? '')),
            '{{company}}'    => $lead->company ?? '',
            '{{website}}'    => $lead->website ?? '',
            '{{email}}'      => $lead->email,
        ];

        return str_replace(array_keys($variables), array_values($variables), $template);
    }
}
