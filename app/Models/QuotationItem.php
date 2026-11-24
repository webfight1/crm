<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
        'type',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'subtotal',
        'sort_order'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2'
    ];

    /** A text-only line (heading/explanation) without quantity or price. */
    public function isText(): bool
    {
        return $this->type === 'text';
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->sort_order)) {
                $maxOrder = static::where('quotation_id', $item->quotation_id)->max('sort_order');
                $item->sort_order = ($maxOrder ?? 0) + 1;
            }
        });

        static::saving(function ($item) {
            if ($item->type === 'text') {
                $item->quantity = 0;
                $item->unit = '';
                $item->unit_price = 0;
            }
            $item->subtotal = $item->quantity * $item->unit_price;
        });
    }
    //
}
