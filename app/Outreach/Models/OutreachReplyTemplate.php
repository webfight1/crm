<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One saved reply snippet, owned by a user, that can be selected from the
 * inbox thread reply form to pre-fill subject + body.
 */
class OutreachReplyTemplate extends Model
{
    protected $table = 'outreach_reply_templates';

    protected $fillable = [
        'user_id',
        'name',
        'subject',
        'body',
        'sort_order',
    ];
}
