<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deal extends Model
{
    protected $fillable = [
        'title',
        'description',
        'value',
        'stage',
        'probability',
        'expected_close_date',
        'actual_close_date',
        'notes',
        'clarity_level',
        'revenue_model',
        'retainer_amount',
        'retainer_note',
        'retainer_start',
        'retainer_months',
        'retainer_day',
        'estimated_hours',
        'work_type',
        'risk_level',
        'is_fast_cash',
        'customer_id',
        'company_id',
        'contact_id',
        'user_id',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'expected_close_date' => 'date',
        'actual_close_date' => 'date',
        'is_fast_cash' => 'boolean',
        'estimated_hours' => 'integer',
        'retainer_amount' => 'decimal:2',
        'retainer_start' => 'date',
        'retainer_months' => 'integer',
        'retainer_day' => 'integer',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->whereNotIn('stage', ['closed_won', 'closed_lost']);
    }

    public function scopeClosed($query)
    {
        return $query->whereIn('stage', ['closed_won', 'closed_lost']);
    }

    public function scopeWon($query)
    {
        return $query->where('stage', 'closed_won');
    }

    public function scopeLost($query)
    {
        return $query->where('stage', 'closed_lost');
    }
}
