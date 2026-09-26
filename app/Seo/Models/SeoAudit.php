<?php

namespace App\Seo\Models;

use App\Models\Deal;
use App\Models\Quotation;
use App\Outreach\Models\OutreachLead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoAudit extends Model
{
    protected $table = 'seo_audits';

    public const STATUS_PENDING = 'pending';
    public const STATUS_DONE    = 'done';
    public const STATUS_FAILED  = 'failed';

    // Per-check result statuses stored in `results`.
    public const RESULT_PASS = 'pass';
    public const RESULT_FAIL = 'fail';
    public const RESULT_SKIP = 'skip';

    protected $fillable = [
        'lead_id', 'main_audit_id', 'deal_id', 'quotation_id', 'url', 'keyword', 'page_source', 'page_note', 'site_type', 'site_type_note', 'status',
        'score', 'results', 'extras', 'summary', 'error', 'completed_at',
    ];

    protected $casts = [
        'results'      => 'array',
        'extras'       => 'array',
        'score'        => 'integer',
        'completed_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(OutreachLead::class, 'lead_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /** Main audits only — extra pages of a client hang off one (main_audit_id). */
    public function scopeMain(Builder $query): Builder
    {
        return $query->whereNull('main_audit_id');
    }

    public function mainAudit(): BelongsTo
    {
        return $this->belongsTo(self::class, 'main_audit_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(self::class, 'main_audit_id')->orderBy('id');
    }

    /** The client's main audit: itself, or the one this extra page belongs to. */
    public function root(): self
    {
        return $this->main_audit_id ? ($this->mainAudit ?? $this) : $this;
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** @return array<int, array<string, mixed>> */
    public function failedResults(): array
    {
        return array_values(array_filter(
            $this->results ?? [],
            fn ($r) => ($r['status'] ?? null) === self::RESULT_FAIL
        ));
    }
}
