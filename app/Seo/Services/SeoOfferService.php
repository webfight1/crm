<?php

namespace App\Seo\Services;

use App\Models\Quotation;
use App\Models\Setting;
use App\Seo\Models\SeoAudit;
use App\Seo\Models\SeoAuditCheck;
use App\Seo\Playbook;
use Illuminate\Support\Facades\DB;

/**
 * Builds a DRAFT quotation from an audit: Playbook base items + one line per
 * failed check that has a priced fix. Never sends anything.
 */
class SeoOfferService
{
    public function createQuotation(SeoAudit $audit, ?int $userId = null): Quotation
    {
        if (! $audit->deal_id) {
            throw new \RuntimeException('Auditil pole tehingut — pakkumist ei saa siduda.');
        }
        if ($audit->quotation_id && ($existing = Quotation::find($audit->quotation_id))) {
            return $existing;
        }

        $items = $this->items($audit);
        if (! $items) {
            throw new \RuntimeException('Pakkumisse pole ühtegi rida — lisa Playbookis hinnaga parandusi või põhiread.');
        }

        $settings = Setting::getSettings();
        $lead     = $audit->lead;
        $deal     = $audit->deal;

        return DB::transaction(function () use ($audit, $items, $settings, $lead, $deal, $userId) {
            $quotation = new Quotation([
                'deal_id'     => $audit->deal_id,
                'user_id'     => $userId ?? $deal->user_id,
                'title'       => mb_substr($this->placeholders(Playbook::get('offer.title'), $audit), 0, 255),
                'description' => $audit->summary,
                'vat_rate'    => $settings->default_vat_rate ?? 24,
                'valid_until' => now()->addDays(max(1, Playbook::int('offer.valid_days'))),
                'terms'       => $settings->quotation_terms,
                'notes'       => Playbook::get('offer.notes') ?: null,
                'status'      => 'draft',
                'subtotal'    => 0,
                'vat_amount'  => 0,
                'total'       => 0,
            ]);
            $quotation->number = Quotation::nextNumber();
            $quotation->save();

            foreach ($items as $item) {
                $quotation->items()->create($item);
            }

            $quotation->load('items')->calculateTotals()->save();
            $deal->update(['value' => $quotation->subtotal]);
            $audit->update(['quotation_id' => $quotation->id]);

            return $quotation;
        });
    }

    /** @return array<int, array{description:string, quantity:float, unit:string, unit_price:float}> */
    public function items(SeoAudit $audit): array
    {
        $items = [];

        foreach (Playbook::lines('offer.base_items') as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 4 || ! is_numeric($parts[1]) || ! is_numeric(str_replace(',', '.', $parts[3]))) {
                continue;
            }
            $items[] = [
                'description' => $parts[0],
                'quantity'    => (float) $parts[1],
                'unit'        => $parts[2] ?: 'tk',
                'unit_price'  => (float) str_replace(',', '.', $parts[3]),
            ];
        }

        $failedKeys = array_column($audit->failedResults(), 'key');
        $checks = SeoAuditCheck::whereIn('key', $failedKeys)
            ->whereNotNull('fix_title')->where('fix_price', '>', 0)
            ->orderByDesc('weight')->orderBy('sort_order')
            ->get();

        $seen = array_column($items, 'description');
        foreach ($checks as $check) {
            if (in_array($check->fix_title, $seen, true)) {
                continue; // several checks can share one fix
            }
            $seen[] = $check->fix_title;
            $items[] = [
                'description' => $check->fix_title,
                'quantity'    => (float) $check->fix_quantity,
                'unit'        => $check->fix_unit ?: 'tk',
                'unit_price'  => (float) $check->fix_price,
            ];
        }

        return $items;
    }

    private function placeholders(string $text, SeoAudit $audit): string
    {
        $lead = $audit->lead;

        return trim(strtr($text, [
            '{{company}}'  => (string) ($lead?->company ?: $audit->deal?->company?->name ?: parse_url($audit->url, PHP_URL_HOST)),
            '{{keyword}}'  => (string) ($audit->keyword ?? ''),
            '{{position}}' => (string) ($lead?->serp_position ?? ''),
            '{{website}}'  => (string) $audit->url,
        ]));
    }
}
