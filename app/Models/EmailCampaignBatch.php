<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaignBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'csv_filename',
        'subject',
        'subject_ru',
        'message',
        'message_ru',
        'total_emails',
        'sent_emails',
        'failed_emails',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(EmailCampaign::class, 'batch_id');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function updateProgress()
    {
        $sent = $this->campaigns()->where('status', 'sent')->count();
        $failed = $this->campaigns()->where('status', 'failed')->count();
        $pending = $this->campaigns()->where('status', 'pending')->count();

        $this->update([
            'sent_emails' => $sent,
            'failed_emails' => $failed,
            'status' => $pending > 0 ? 'sending' : 'completed',
            'completed_at' => $pending === 0 ? now() : null,
        ]);
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->total_emails === 0) return 0;
        return round(($this->sent_emails + $this->failed_emails) / $this->total_emails * 100, 1);
    }
}
