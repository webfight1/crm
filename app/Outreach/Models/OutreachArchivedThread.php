<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Archive flag for one inbox "thread" (= unique from-email address).
 *
 * Presence of a row means the thread is hidden from the default inbox view.
 * Removed automatically when a new inbound message arrives, so the thread
 * resurfaces — see ReplyDetectionService::persistMessage.
 */
class OutreachArchivedThread extends Model
{
    protected $table = 'outreach_archived_threads';

    public $timestamps = false;

    protected $fillable = [
        'email_lower',
        'archived_at',
        'archived_by_user_id',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];
}
