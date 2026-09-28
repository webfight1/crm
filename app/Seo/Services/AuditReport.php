<?php

namespace App\Seo\Services;

use App\Models\Setting;
use App\Seo\Models\SeoAudit;
use App\Seo\Models\SeoAuditCheck;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Client-facing technical SEO report of one audited page (or all of a
 * client's pages): what's fine, what needs fixing and why — sent from the
 * audit page to back up the offer.
 */
class AuditReport
{
    /** @return Collection<int, SeoAudit> the page itself, or the client's main page + extra pages */
    public function pages(SeoAudit $audit, bool $all): Collection
    {
        $root = $audit->root();
        $pages = $all ? collect([$root])->concat($root->pages()->get()) : collect([$audit]);

        return $pages->filter(fn (SeoAudit $a) => $a->status === SeoAudit::STATUS_DONE && $a->results)->values();
    }

    /** @return array{url:string, keyword:?string, score:?int, failed:array, passed:array} per page */
    public function sections(Collection $pages): array
    {
        // Audits made before results carried the client explanation get it from the check.
        $explain = SeoAuditCheck::pluck('client_explanation', 'key');

        return $pages->map(function (SeoAudit $a) use ($explain) {
            $rows = collect($a->results)->map(fn ($r) => $r + ['explanation' => $r['explanation'] ?? $explain[$r['key'] ?? ''] ?? null]);

            return [
                'url'     => $a->url,
                'keyword' => $a->keyword,
                'score'   => $a->score,
                'failed'  => $rows->where('status', SeoAudit::RESULT_FAIL)->sortByDesc('weight')->values()->all(),
                'passed'  => $rows->where('status', SeoAudit::RESULT_PASS)->values()->all(),
            ];
        })->all();
    }

    public function pdf(SeoAudit $audit, bool $all): string
    {
        $pages = $this->pages($audit, $all);

        return Pdf::loadView('seo.audits.report-pdf', [
            'site'     => $this->site($audit),
            'summary'  => $all || ! $audit->main_audit_id ? $audit->root()->summary : $audit->summary,
            'sections' => $this->sections($pages),
            'settings' => Setting::getSettings(),
        ])->output();
    }

    public function fileName(SeoAudit $audit): string
    {
        return 'seo-ulevaade_' . Str::slug($this->site($audit)) . '_' . now()->format('Y-m-d') . '.pdf';
    }

    public function site(SeoAudit $audit): string
    {
        return preg_replace('/^www\./', '', (string) parse_url($audit->url, PHP_URL_HOST)) ?: $audit->url;
    }
}
