<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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

    /**
     * Description with light formatting for the view and PDF:
     * "## Pealkiri", "**paks**", "- loetelu"; single line breaks are kept.
     * Typed <b>/<strong>/<br> are honoured, any other HTML is stripped.
     */
    public function formattedDescription(): HtmlString
    {
        $text = preg_replace(['~</?(b|strong)\s*>~i', '~<br\s*/?>~i'], ['**', "\n"], (string) $this->description);

        return new HtmlString(Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'renderer' => ['soft_break' => "<br>\n"],
        ]));
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
