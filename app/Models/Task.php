<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Task extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title',
        'description',
        'type',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'notes',
        'customer_id',
        'company_id',
        'contact_id',
        'deal_id',
        'user_id',
        'assignee_id',
        'price',
        'is_favorite',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'price' => 'decimal:2',
        'is_favorite' => 'boolean',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at', 'desc');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function commentRead(): HasOne
    {
        return $this->hasOne(CommentRead::class)
            ->where('user_id', Auth::id());
    }

    public function hasUnreadComments(): bool
    {
        $lastRead = $this->commentRead?->last_read_at;
        
        // Kui pole kunagi lugenud ja on kommentaare teistelt kasutajatelt
        if (!$lastRead) {
            return $this->comments()
                ->where('user_id', '!=', Auth::id())
                ->exists();
        }

        // Kontrolli kas on uuemaid kommentaare teistelt kasutajatelt
        return $this->comments()
            ->where('user_id', '!=', Auth::id())
            ->where('created_at', '>', $lastRead)
            ->exists();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->whereNotIn('status', ['completed', 'cancelled']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('due_date', today());
    }
}
