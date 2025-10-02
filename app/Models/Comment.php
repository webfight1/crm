<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Comment extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'content',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function userReads(): HasMany
    {
        return $this->hasMany(CommentUserRead::class);
    }

    public function isUnread(): bool
    {
        return !$this->userReads()
            ->where('user_id', Auth::id())
            ->exists();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
