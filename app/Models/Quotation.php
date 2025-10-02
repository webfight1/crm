<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number',
        'deal_id',
        'user_id',
        'title',
        'description',
        'subtotal',
        'vat_rate',
        'vat_amount',
        'total',
        'status',
        'valid_until',
        'terms',
        'notes'
    ];

    protected $casts = [
        'valid_until' => 'date',
        'subtotal' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function calculateTotals()
    {
        $this->subtotal = $this->items->sum('subtotal');
        $this->vat_amount = $this->subtotal * ($this->vat_rate / 100);
        $this->total = $this->subtotal + $this->vat_amount;
        return $this;
    }
}
