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
    public static function isInCooldown(string $email, ?int $userId = null, int $cooldownDays = 14): bool
    {
        // Ensure cooldownDays is an integer
        $days = (int)$cooldownDays;
        $cooldownDate = Carbon::now()->subDays($days);
        
        $query = self::where('email', $email)
            ->where('status', 'sent')
            ->where('sent_at', '>=', $cooldownDate);
            
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        
        return $query->exists();
    }

    /**
     * Get emails that are in cooldown period
     */
    public static function getEmailsInCooldown(?int $userId = null, int $cooldownDays = 14): array
    {
        // Ensure cooldownDays is an integer
        $days = (int)$cooldownDays;
        $cooldownDate = Carbon::now()->subDays($days);
        
        $query = self::where('status', 'sent')
            ->where('sent_at', '>=', $cooldownDate);
            
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        
        return $query->pluck('email')
            ->unique()
            ->toArray();
    }

    /**
     * Log successful email send
     */
    public static function logSent(
        string $email,
        string $subject,
        ?int $userId = null,
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
