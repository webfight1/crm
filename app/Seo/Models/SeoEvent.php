<?php

namespace App\Seo\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line in a client's SEO log (see App\Seo\Services\ClientLog). */
class SeoEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'seo_events';

    protected $fillable = ['lead_id', 'type', 'title', 'body', 'url', 'user_id', 'created_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
