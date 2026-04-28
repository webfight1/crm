<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class OutreachEmailAccount extends Model
{
    protected $table = 'outreach_email_accounts';

    /**
     * After this many consecutive failures the inbox is auto-disabled.
     * Chosen to tolerate transient network glitches (1–2 in a row) while
     * quickly catching a broken credential or suspended account (5 in a row).
     */
    public const FAILURE_THRESHOLD = 5;

    protected $fillable = [
        'name',
        'email',
        'provider',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'imap_host',
        'imap_port',
        'imap_username',
        'imap_password',
        'imap_encryption',
        'daily_limit',
        'sent_today',
        'last_sent_at',
        'is_active',
        'is_primary_reply_account',
        'consecutive_failures',
        'last_error',
        'disabled_at',
    ];

    protected $casts = [
        'smtp_port'                => 'integer',
        'imap_port'                => 'integer',
        'daily_limit'              => 'integer',
        'sent_today'               => 'integer',
        'is_active'                => 'boolean',
        'is_primary_reply_account' => 'boolean',
        'last_sent_at'             => 'datetime',
        'consecutive_failures'     => 'integer',
        'disabled_at'              => 'datetime',
    ];

    protected $hidden = ['smtp_password', 'imap_password'];

    // ─── Accessors / Mutators ───────────────────────────────────────────────

    public function setSmtpPasswordAttribute(?string $value): void
    {
        $this->attributes['smtp_password'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getSmtpPasswordAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setImapPasswordAttribute(?string $value): void
    {
        $this->attributes['imap_password'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getImapPasswordAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // ─── Relationships ──────────────────────────────────────────────────────

    public function leads(): HasMany
    {
        return $this->hasMany(OutreachLead::class, 'assigned_email_account_id');
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(OutreachSendLog::class, 'email_account_id');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    public function hasCapacity(): bool
    {
        return $this->is_active && $this->sent_today < $this->daily_limit;
    }

    public function incrementSentToday(): void
    {
        $this->increment('sent_today');
        $this->update(['last_sent_at' => now()]);
    }

    // ─── Health Management ──────────────────────────────────────────────────

    /**
     * Record a failure against this inbox.
     *
     * Increments consecutive_failures and stores the error message.
     * When the threshold is reached the inbox is automatically disabled
     * (is_active = false) and disabled_at is set.
     *
     * This method uses a raw DB increment to avoid a read-modify-write race
     * when multiple workers report failures at the same time.
     */
    public function recordFailure(string $errorMessage): void
    {
        \Illuminate\Support\Facades\DB::table('outreach_email_accounts')
            ->where('id', $this->id)
            ->update([
                'consecutive_failures' => \Illuminate\Support\Facades\DB::raw('consecutive_failures + 1'),
                'last_error'           => $errorMessage,
            ]);

        $this->refresh();

        if ($this->consecutive_failures >= self::FAILURE_THRESHOLD && $this->is_active) {
            $this->update([
                'is_active'   => false,
                'disabled_at' => now(),
            ]);
        }
    }

    /**
     * Reset the failure counter after a successful send.
     * Also clears last_error so stale error messages don't linger.
     */
    public function resetFailureCount(): void
    {
        if ($this->consecutive_failures !== 0 || $this->last_error !== null) {
            $this->update([
                'consecutive_failures' => 0,
                'last_error'           => null,
            ]);
        }
    }

    /**
     * Return true if the inbox is active and below its failure threshold.
     * InboxRotationService uses this to skip degraded accounts.
     */
    public function isHealthy(): bool
    {
        return $this->is_active
            && $this->consecutive_failures < self::FAILURE_THRESHOLD;
    }

    /**
     * Return the single account flagged as the primary reply mailbox, or null
     * if none has been configured. The controller enforces single-primary on
     * write so first() is sufficient even though the column is non-unique.
     */
    public static function primaryReplyAccount(): ?self
    {
        return static::where('is_primary_reply_account', true)->first();
    }
}
