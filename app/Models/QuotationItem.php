<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
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
            $item->subtotal = $item->quantity * $item->unit_price;
        });
    }
    //
}
