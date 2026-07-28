<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class OutreachCampaignStep extends Model
{
    protected $table = 'outreach_campaign_steps';

    protected $fillable = [
        'campaign_id',
        'step_order',
        'day_offset',
        'subject',
        'body_template',
        'attachments',
    ];

    protected $casts = [
        'step_order'  => 'integer',
        'day_offset'  => 'integer',
        'attachments' => 'array',
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
     *   {{ai_line}}, {{lcp}} / {{lcp_mobile}}, {{performance_score}}
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

    /**
     * Build the attachment array in the shape OutreachMailer::send() expects:
     * a list of ['path' => <absolute>, 'name' => <display>, 'mime' => <type>].
     *
     * Stored `path` values are relative to the `local` disk; they are resolved
     * to absolute filesystem paths here. Entries whose file no longer exists
     * are skipped so a deleted file never breaks a send.
     */
    public function attachmentsForMailer(): array
    {
        $out = [];

        foreach ($this->attachments ?? [] as $a) {
            if (empty($a['path'])) {
                continue;
            }

            $absolute = Storage::disk('local')->path($a['path']);

            if (! is_file($absolute)) {
                continue;
            }

            $out[] = [
                'path' => $absolute,
                'name' => $a['name'] ?? basename($absolute),
                'mime' => $a['mime'] ?? null,
            ];
        }

        return $out;
    }

    private function replaceVariables(string $template, OutreachLead $lead): string
    {
        $variables = [
            '{{first_name}}'        => $lead->first_name,
            '{{last_name}}'         => $lead->last_name ?? '',
            '{{full_name}}'         => trim("{$lead->first_name} " . ($lead->last_name ?? '')),
            '{{company}}'           => $lead->company ?? '',
            '{{company_short}}'     => $this->cleanCompany($lead->company),
            '{{website}}'           => $lead->website ?? '',
            '{{industry}}'          => $lead->industry ?? '',
            '{{email}}'             => $lead->email,
            '{{lcp}}'               => $lead->lcp_mobile ?? '',
            '{{lcp_mobile}}'        => $lead->lcp_mobile ?? '',
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

    /**
     * Company name with its Estonian (and a few common foreign) legal form
     * stripped, so "Inox Baltic OÜ" → "Inox Baltic" for greetings like
     * "Tere {{company_short}} tiim!". The form is removed whether it sits at
     * the start ("AS Tallink") or end ("Webfight OÜ"), with any surrounding
     * comma/period/whitespace. Names without a form are returned unchanged.
     */
    private function cleanCompany(?string $company): string
    {
        $name = trim((string) $company);
        if ($name === '') {
            return '';
        }

        // Whole-word legal forms (case-insensitive, UTF-8). Add more as needed.
        $forms = 'OÜ|AS|MTÜ|FIE|TÜ|UÜ|SA|KÜ|MÜ|Ltd|LLC|Inc|OY|OYj|AB|GmbH';

        // Trailing form: "Webfight OÜ", "Baltic AS," (allow trailing , . space)
        $name = preg_replace('/[\s,]+(?:' . $forms . ')[.,\s]*$/ui', '', $name);
        // Leading form: "AS Tallink", "OÜ Webfight"
        $name = preg_replace('/^(?:' . $forms . ')[.,\s]+/ui', '', $name);

        return trim($name);
    }
}
