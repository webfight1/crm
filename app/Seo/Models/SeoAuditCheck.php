<?php

namespace App\Seo\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SeoAuditCheck extends Model
{
    protected $table = 'seo_audit_checks';

    public const TYPE_BUILTIN = 'builtin';
    public const TYPE_AI      = 'ai';

    protected $fillable = [
        'key', 'type', 'label', 'question', 'client_explanation', 'enabled',
        'weight', 'fix_title', 'fix_price', 'fix_quantity', 'fix_unit', 'sort_order',
    ];

    protected $casts = [
        'enabled'      => 'boolean',
        'weight'       => 'integer',
        'fix_price'    => 'decimal:2',
        'fix_quantity' => 'decimal:2',
        'sort_order'   => 'integer',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('enabled', true)->orderBy('sort_order')->orderBy('id');
    }

    public function isBuiltin(): bool
    {
        return $this->type === self::TYPE_BUILTIN;
    }
}
