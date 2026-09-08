<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachCampaign extends Model
{
    protected $table = 'outreach_campaigns';

    protected $fillable = [
        'name',
        'description',
        'unsubscribe_html',
        'ai_prompt',
        'daily_limit',
        'reply_stop_enabled',
        'use_ai_line',
        'is_active',
    ];

    protected $casts = [
        'daily_limit'        => 'integer',
        'reply_stop_enabled' => 'boolean',
        'use_ai_line'        => 'boolean',
        'is_active'          => 'boolean',
    ];

    // ─── Relationships ──────────────────────────────────────────────────────

    public function steps(): HasMany
    {
        return $this->hasMany(OutreachCampaignStep::class, 'campaign_id')
                    ->orderBy('step_order');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(OutreachLead::class, 'campaign_id');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(OutreachSendLog::class, 'campaign_id');
    }

    /**
     * Mailboxes this campaign is restricted to sending from. Empty = no
     * restriction (rotate across every active sending inbox, the default).
     */
    public function sendingAccounts(): BelongsToMany
    {
        return $this->belongsToMany(
            OutreachEmailAccount::class,
            'outreach_campaign_email_accounts',
            'campaign_id',
            'email_account_id',
        );
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /**
     * IDs of the mailboxes this campaign may send from. Empty array means
     * "no restriction" — InboxRotationService then uses every active inbox.
     */
    public function sendingAccountIds(): array
    {
        return $this->sendingAccounts()->pluck('outreach_email_accounts.id')->all();
    }

    public function getStepAt(int $stepOrder): ?OutreachCampaignStep
    {
        return $this->steps()->where('step_order', $stepOrder)->first();
    }

    public function sentTodayCount(): int
    {
        return $this->sendLogs()
            ->where('status', 'sent')
            ->whereDate('sent_at', today())
            ->count();
    }

    public function isOverDailyLimit(): bool
    {
        if (is_null($this->daily_limit)) {
            return false;
        }

        return $this->sentTodayCount() >= $this->daily_limit;
    }

    /**
     * True when at least one step references a PageSpeed placeholder.
     *
     * Used to scope business rules that only apply to speed-optimisation
     * campaigns (e.g. "skip fast sites"). Campaigns that don't mention speed
     * in any step remain unaffected by those rules.
     */
    public function pitchesSpeedOptimization(): bool
    {
        foreach ($this->steps as $step) {
            $text = $step->subject . $step->body_template;
            if (str_contains($text, '{{lcp_mobile}}')
                || str_contains($text, '{{lcp}}')
                || str_contains($text, '{{performance_score}}')
            ) {
                return true;
            }
        }
        return false;
    }
}
