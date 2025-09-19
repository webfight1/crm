<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EmailLog extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'subject',
        'email_campaign_id',
        'status',
        'response',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailCampaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class);
    }

    /**
     * Check if email was sent to this address within cooldown period
     */
    public static function isInCooldown(string $email, int $userId, int $cooldownDays = 14): bool
    {
        $cooldownDate = Carbon::now()->subDays($cooldownDays);
        
        return self::where('email', $email)
            ->where('user_id', $userId)
            ->where('status', 'sent')
            ->where('sent_at', '>=', $cooldownDate)
            ->exists();
    }

    /**
     * Get emails that are in cooldown period
     */
    public static function getEmailsInCooldown(int $userId, int $cooldownDays = 14): array
    {
        $cooldownDate = Carbon::now()->subDays($cooldownDays);
        
        return self::where('user_id', $userId)
            ->where('status', 'sent')
            ->where('sent_at', '>=', $cooldownDate)
            ->pluck('email')
            ->unique()
            ->toArray();
    }

    /**
     * Log successful email send
     */
    public static function logSent(
        int $userId,
        string $email,
        string $subject,
        ?int $campaignId = null,
        ?string $response = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'email' => $email,
            'subject' => $subject,
            'email_campaign_id' => $campaignId,
            'status' => 'sent',
            'response' => $response,
            'sent_at' => Carbon::now(),
        ]);
    }
}
