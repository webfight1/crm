<?php

namespace App\Chats\Models;

use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatThread extends Model
{
    protected $fillable = [
        'network', 'external_id', 'phone', 'name', 'is_group',
        'customer_id', 'contact_id', 'is_monitored', 'auto_ai',
        'last_message_at', 'triaged_at', 'ai_triage',
    ];

    protected $casts = [
        'is_group'        => 'boolean',
        'is_monitored'    => 'boolean',
        'auto_ai'         => 'boolean',
        'last_message_at' => 'datetime',
        'triaged_at'      => 'datetime',
        'ai_triage'       => 'array',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class)->latestOfMany('sent_at');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function networkLabel(): string
    {
        return $this->network === 'messenger' ? 'Messenger' : 'WhatsApp';
    }

    public function displayName(): string
    {
        return $this->contact?->full_name
            ?? $this->customer?->full_name
            ?? $this->name
            ?? ($this->phone ? '+' . $this->phone : $this->external_id);
    }
}
