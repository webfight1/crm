<?php

namespace App\Seo\Services;

use App\Models\Quotation;
use App\Models\Setting;
use App\Seo\Models\SeoAudit;
use App\Seo\Models\SeoAuditCheck;
use App\Seo\Playbook;
use Illuminate\Support\Facades\DB;

/**
 * Builds a DRAFT quotation from an audit: Playbook base items + the priced
 * fixes of failed checks + content marketing when the site is technically
 * fine (score ≥ content.min_score): no blog → blog setup, blog → weekly
 * articles (Playbook content.*). With offer.group_items, fixes sharing a fix_group
 * become one line ("Tehniline SEO korrastus: HTTPS, sitemap …", price = sum).
 *
 * A client with extra pages (audits hanging off the main one) gets ONE
 * quotation: site-wide fixes (offer.site_wide_checks) once, and one line per
 * page for that page's own fixes. Never sends anything.
 */
class SeoOfferService
{
    /**
     * The client's quotation (main audit + its extra pages). An existing one is
     * returned as-is — or, with $rebuild and still a draft, its lines and
     * description are built again from all pages.
     */
    public function createQuotation(SeoAudit $audit, ?int $userId = null, bool $rebuild = false): Quotation
    {
        $audit = $audit->root();
        if (! $audit->deal_id) {
            throw new \RuntimeException('Auditil pole tehingut — pakkumist ei saa siduda.');
        }
        $pages = $audit->pages()->where('status', SeoAudit::STATUS_DONE)->get();

        $existing = $audit->quotation_id ? Quotation::find($audit->quotation_id) : null;
        if ($existing && ! ($rebuild && $existing->status === 'draft')) {
            return $existing;
        }

        $items = $pages->isEmpty() ? $this->items($audit) : $this->multiPageItems($audit, $pages->all());
        if (! $items) {
            throw new \RuntimeException('Pakkumisse pole ühtegi rida — lisa Playbookis hinnaga parandusi või põhiread.');
        }
        $description = $this->description($audit, $pages->all());

        if ($existing) {
            return DB::transaction(function () use ($existing, $items, $description, $audit, $pages) {
                $existing->items()->delete();
                foreach ($items as $item) {
                    $existing->items()->create($item);
                }
                $existing->description = $description;
                $existing->load('items')->calculateTotals()->save();
                $audit->deal?->update(['value' => $existing->subtotal]);
                SeoAudit::whereIn('id', $pages->pluck('id'))->update(['quotation_id' => $existing->id]);

                return $existing;
            });
        }

        $settings = Setting::getSettings();
        $deal     = $audit->deal;

        return DB::transaction(function () use ($audit, $pages, $items, $description, $settings, $deal, $userId) {
            $quotation = new Quotation([
                'deal_id'     => $audit->deal_id,
                'user_id'     => $userId ?? $deal->user_id,
                'title'       => mb_substr($this->placeholders(Playbook::get('offer.title'), $audit), 0, 255),
                'description' => $description,
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
            SeoAudit::whereIn('id', $pages->pluck('id')->push($audit->id))->update(['quotation_id' => $quotation->id]);
            $audit->quotation_id = $quotation->id;

            return $quotation;
        });
    }

    /**
     * Main + extra pages: base lines once, site-wide fixes once (from any
     * page), each page's own fixes as one line, content lines by the main audit.
     *
     * @param  SeoAudit[] $pages
     * @return array<int, array{description:string, quantity:float, unit:string, unit_price:float}>
     */
    public function multiPageItems(SeoAudit $main, array $pages): array
    {
        $siteWide = Playbook::lines('offer.site_wide_checks');
        $all = array_merge([$main], $pages);

        $items = $this->lineItems('offer.base_items', $main);

        $siteKeys = [];
        foreach ($all as $a) {
            $siteKeys = array_merge($siteKeys, array_intersect(array_column($a->failedResults(), 'key'), $siteWide));
        }
        $items = array_merge($items, $this->fixItems(array_unique($siteKeys), array_column($items, 'description')));

        foreach ($all as $a) {
            $pageKeys = array_diff(array_column($a->failedResults(), 'key'), $siteWide);
            $fixes = $this->fixItems($pageKeys, [], false);
            if (! $fixes) {
                continue;
            }
            $path = parse_url($a->url, PHP_URL_PATH) ?: '/';
            $items[] = [
                'description' => mb_substr('Leht ' . $path . ($a->keyword ? " („{$a->keyword}“)" : '') . ': '
                    . implode(', ', array_column($fixes, 'description')), 0, 255),
                'quantity'    => 1,
                'unit'        => 'leht',
                'unit_price'  => round(array_sum(array_map(fn ($f) => $f['quantity'] * $f['unit_price'], $fixes)), 2),
            ];
        }

        return array_merge($items, $this->contentItems($main));
    }

    /** Main summary + the list of pages audited with it. */
    public function description(SeoAudit $main, array $pages): ?string
    {
        if (! $pages) {
            return $main->summary;
        }
        $list = array_map(
            fn (SeoAudit $a) => '• ' . $a->url . ($a->keyword ? " („{$a->keyword}“)" : '') . ($a->score !== null ? " — {$a->score}/100" : ''),
            array_merge([$main], $pages),
        );

        return trim(($main->summary ?? '') . "\n\nAuditeeritud lehed:\n" . implode("\n", $list));
    }

    /** @return array<int, array{description:string, quantity:float, unit:string, unit_price:float}> */
    public function items(SeoAudit $audit): array
    {
        $items = $this->lineItems('offer.base_items', $audit);
        $items = array_merge($items, $this->fixItems(array_column($audit->failedResults(), 'key'), array_column($items, 'description')));

        return array_merge($items, $this->contentItems($audit));
    }

    /**
     * Priced fixes of the given failed checks; one line per fix, or per
     * fix_group with offer.group_items (and $group).
     *
     * @param  string[] $failedKeys
     * @param  string[] $seen fix titles already on the quotation
     * @return array<int, array{description:string, quantity:float, unit:string, unit_price:float}>
     */
    private function fixItems(array $failedKeys, array $seen = [], bool $group = true): array
    {
        $items = [];
        $checks = SeoAuditCheck::whereIn('key', $failedKeys)
            ->whereNotNull('fix_title')->where('fix_price', '>', 0)
            ->orderByDesc('weight')->orderBy('sort_order')
            ->get();

        $group = $group && Playbook::bool('offer.group_items');
        $grouped = []; // group => ['works' => string[], 'total' => float], in first-seen order
        foreach ($checks as $check) {
            if (in_array($check->fix_title, $seen, true)) {
                continue; // several checks can share one fix
            }
            $seen[] = $check->fix_title;

            if ($group && $check->fix_group) {
                $grouped[$check->fix_group]['works'][] = $check->fix_title;
                $grouped[$check->fix_group]['total'] = ($grouped[$check->fix_group]['total'] ?? 0)
                    + (float) $check->fix_price * (float) $check->fix_quantity;
                continue;
            }
            $items[] = [
                'description' => $check->fix_title,
                'quantity'    => (float) $check->fix_quantity,
                'unit'        => $check->fix_unit ?: 'tk',
                'unit_price'  => (float) $check->fix_price,
            ];
        }

        foreach ($grouped as $name => $g) {
            $items[] = [
                // quotation_items.description is varchar(255)
                'description' => mb_substr(count($g['works']) === 1 ? $g['works'][0] : $name . ': ' . implode(', ', $g['works']), 0, 255),
                'quantity'    => 1,
                'unit'        => count($g['works']) === 1 ? 'tk' : 'komplekt',
                'unit_price'  => round($g['total'], 2),
            ];
        }

        return $items;
    }

    /** Content marketing when the site is technically fine: blog setup, or weekly articles. */
    private function contentItems(SeoAudit $audit): array
    {
        if (! Playbook::bool('content.enabled') || $audit->score === null
            || $audit->score < Playbook::int('content.min_score')
        ) {
            return [];
        }
        $hasBlog = (bool) ($audit->extras['blog']['exists'] ?? false);

        return $this->lineItems($hasBlog ? 'content.articles' : 'content.blog_setup', $audit);
    }

    /**
     * Playbook lines "description | quantity | unit | price" → quotation items.
     *
     * @return array<int, array{description:string, quantity:float, unit:string, unit_price:float}>
     */
    private function lineItems(string $key, SeoAudit $audit): array
    {
        $items = [];
        foreach (Playbook::lines($key) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 4 || ! is_numeric($parts[1]) || ! is_numeric(str_replace(',', '.', $parts[3]))) {
                continue;
            }
            $items[] = [
                'description' => mb_substr($this->placeholders($parts[0], $audit), 0, 255),
                'quantity'    => (float) $parts[1],
                'unit'        => $parts[2] ?: 'tk',
                'unit_price'  => (float) str_replace(',', '.', $parts[3]),
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
