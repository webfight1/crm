<?php

namespace App\Seo\Services;

use App\Outreach\Services\PageSpeedService;
use App\Seo\Models\SeoAudit;
use App\Seo\Models\SeoAuditCheck;
use App\Seo\Playbook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SeoAuditService
 *
 * Runs the Playbook checklist (seo_audit_checks) against one URL:
 *   1. fetch the page
 *   2. builtin checks — PageAnalyzer (HTML) + network checks here
 *   3. ai checks     — all operator-written questions in ONE LLM call
 *   4. weighted score + client-friendly summary (ai.audit_guidelines)
 *
 * Every step degrades gracefully: without an OpenAI key AI checks are
 * skipped and the summary falls back to a plain list of findings.
 */
class SeoAuditService
{
    private const FETCH_TIMEOUT = 15;

    public function __construct(
        private readonly SeoAi $ai,
        private readonly PageSpeedService $pageSpeed,
        private readonly LandingPageFinder $finder,
    ) {}

    /** How the audited page was chosen (seo_audits.page_source). */
    public const PAGE_SOURCES = [
        'csv'    => 'CSV-st (Google\'i reastuv leht)',
        'found'  => 'Leitud märksõna järgi',
        'home'   => 'Avaleht (on märksõnale suunatud)',
        'client' => 'Kliendi kinnitatud',
        'manual' => 'Valitud käsitsi',
        'none'   => 'Märksõna lehte ei leitud — auditeeriti avalehte',
    ];

    public function run(SeoAudit $audit): SeoAudit
    {
        Playbook::flush();
        $audit->update(['status' => SeoAudit::STATUS_PENDING, 'error' => null]);

        $url = $this->normalizeUrl($audit->url);
        if (! $url) {
            return $this->fail($audit, 'Vigane URL.');
        }
        $url = $this->resolvePage($audit, $url);

        try {
            $response = Http::withOptions(['allow_redirects' => ['max' => 5], 'verify' => false])
                ->timeout(self::FETCH_TIMEOUT)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (compatible; WebfightSeoAudit/1.0; +https://webfight.ee)',
                    'Accept-Language' => 'et,en;q=0.7',
                ])
                ->get($url);
        } catch (\Throwable $e) {
            return $this->fail($audit, 'Lehte ei õnnestunud laadida: ' . $e->getMessage());
        }

        if (! $response->successful() || trim($response->body()) === '') {
            return $this->fail($audit, 'Leht vastas HTTP ' . $response->status());
        }

        $finalUrl = (string) ($response->effectiveUri() ?? $url);
        $analyzer = new PageAnalyzer($response->body(), $finalUrl, $audit->keyword);

        $checks  = SeoAuditCheck::active()->get();
        $results = [];
        $aiChecks = [];

        foreach ($checks as $check) {
            if (! $check->isBuiltin()) {
                $aiChecks[] = $check;
                continue;
            }
            $r = $check->key === 'keyword_landing_page'
                ? $this->landingCheck($audit)
                : ($analyzer->check($check->key) ?? $this->networkCheck($check->key, $finalUrl));
            $results[$check->key] = $this->row($check, $r);
        }

        foreach ($this->runAiChecks($aiChecks, $analyzer->facts(), $finalUrl) as $key => $r) {
            $check = collect($aiChecks)->firstWhere('key', $key);
            $results[$key] = $this->row($check, $r);
        }

        // Keep checklist order.
        $ordered = [];
        foreach ($checks as $check) {
            if (isset($results[$check->key])) {
                $ordered[] = $results[$check->key];
            }
        }

        $audit->forceFill([
            'url'          => $finalUrl,
            'results'      => $ordered,
            'score'        => $this->score($ordered),
        ]);
        $audit->summary      = $this->summarize($audit, $analyzer->facts());
        $audit->status       = SeoAudit::STATUS_DONE;
        $audit->completed_at = now();
        $audit->save();

        return $audit;
    }

    // ─── checks ─────────────────────────────────────────────────────────────

    /**
     * Pick the page to audit. A bare domain + keyword → look for the keyword's
     * own page; a URL with a path is taken as given. Decided once per audit —
     * a rerun keeps the page (and source) chosen the first time.
     */
    private function resolvePage(SeoAudit $audit, string $url): string
    {
        if ($audit->page_source) {
            return $url;
        }

        $isRoot = trim((string) parse_url($url, PHP_URL_PATH), '/') === '';
        if (! $audit->keyword || ! $isRoot) {
            $audit->update(['page_source' => $audit->keyword ? 'manual' : null]);
            return $url;
        }

        $found = $this->finder->find($url, $audit->keyword);
        $audit->update(['page_source' => $found['source'], 'page_note' => mb_substr($found['note'], 0, 500)]);

        return $found['url'] ?? $url;
    }

    private function landingCheck(SeoAudit $audit): array
    {
        return match ($audit->page_source) {
            'csv', 'found', 'client' => $this->verdict(true, $audit->url, $audit->page_note),
            'home'   => $this->verdict(true, 'avaleht', $audit->page_note),
            'none'   => $this->verdict(false, 'puudub', $audit->page_note ?: 'Märksõnale vastavat lehte ei leitud.'),
            'manual' => $this->skip('Leht valiti käsitsi.'),
            default  => $this->skip('Märksõna pole teada.'),
        };
    }

    /** Builtin checks that need extra HTTP requests. */
    private function networkCheck(string $key, string $url): array
    {
        $origin = $this->origin($url);

        switch ($key) {
            case 'pagespeed_mobile':
                $ps = $this->pageSpeed->measureUrl($url);
                if (! $ps) {
                    return $this->skip('PageSpeed mõõtmine ebaõnnestus.');
                }
                return $this->verdict(
                    $ps['performance_score'] >= 50,
                    $ps['performance_score'] . '/100, LCP ' . $ps['lcp_mobile'] . 's'
                );

            case 'robots_txt':
                $body = $this->getText($origin . '/robots.txt');
                return $this->verdict($body !== null, $body !== null ? 'olemas' : 'puudub');

            case 'sitemap':
                $robots = $this->getText($origin . '/robots.txt') ?? '';
                if (preg_match('/^\s*sitemap:\s*(\S+)/im', $robots, $m)) {
                    return $this->verdict(true, $m[1]);
                }
                $xml = $this->getText($origin . '/sitemap.xml') ?? $this->getText($origin . '/sitemap_index.xml');
                return $this->verdict($xml !== null && str_contains($xml, '<'), $xml !== null ? 'olemas' : 'puudub');
        }

        return $this->skip("Tundmatu sisseehitatud kontroll \"{$key}\".");
    }

    /**
     * @param  SeoAuditCheck[]      $checks
     * @return array<string, array> key => result
     */
    private function runAiChecks(array $checks, array $facts, string $url): array
    {
        if (! $checks) {
            return [];
        }

        $questions = [];
        foreach ($checks as $c) {
            $questions[$c->key] = $c->question ?: $c->label;
        }

        $answer = $this->ai->json(
            "Sa oled SEO ja veebi kasutatavuse audiitor. Vasta iga küsimuse kohta ainult antud lehe andmete põhjal.\n"
            . "Kui andmetest ei piisa, pane \"pass\": null.\n"
            . 'Vasta JSON-ina: {"<võti>": {"pass": true|false|null, "note": "lühike põhjendus eesti keeles"}, …}',
            "URL: {$url}\n\nLehe andmed:\n" . json_encode($this->aiFacts($facts), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            . "\n\nKüsimused:\n" . json_encode($questions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );

        $out = [];
        foreach ($checks as $c) {
            $a = $answer[$c->key] ?? null;
            if (! is_array($a) || ! is_bool($a['pass'] ?? null)) {
                $out[$c->key] = $this->skip($answer === null ? 'AI pole saadaval.' : ($a['note'] ?? 'AI ei osanud vastata.'));
                continue;
            }
            $out[$c->key] = $this->verdict($a['pass'], null, $a['note'] ?? null);
        }

        return $out;
    }

    // ─── score + summary ────────────────────────────────────────────────────

    /** Weighted share of passed checks among evaluated (non-skipped) ones. */
    public function score(array $results): ?int
    {
        $total = $passed = 0;
        foreach ($results as $r) {
            if ($r['status'] === SeoAudit::RESULT_SKIP) {
                continue;
            }
            $total += $r['weight'];
            if ($r['status'] === SeoAudit::RESULT_PASS) {
                $passed += $r['weight'];
            }
        }

        return $total > 0 ? (int) round($passed / $total * 100) : null;
    }

    private function summarize(SeoAudit $audit, array $facts): string
    {
        $failed = array_filter($audit->results ?? [], fn ($r) => $r['status'] === SeoAudit::RESULT_FAIL);
        usort($failed, fn ($a, $b) => $b['weight'] <=> $a['weight']);

        if (! $failed) {
            return 'Tehniline põhi on korras — kõik kontrollitud punktid läbisid. Kasv tuleb pigem sisust ja linkidest.';
        }

        $lead = $audit->lead;
        $findings = array_map(fn ($r) => [
            'probleem'       => $r['label'],
            'leitud'         => $r['note'] ?? $r['value'],
            'miks_oluline'   => $r['explanation'],
            'olulisus_1_5'   => $r['weight'],
        ], $failed);

        $answer = $this->ai->json(
            "Sa kirjutad SEO auditi kokkuvõtte, mis läheb kliendile saadetava pakkumise kirjeldusse.\n"
            . "Juhised:\n" . Playbook::get('ai.audit_guidelines') . "\n\n"
            . 'Vasta JSON-ina: {"summary": "kokkuvõtte tekst, lõigud eraldatud \\n\\n"}',
            json_encode([
                'ettevõte'        => $lead?->company,
                'leht'            => $audit->url,
                'lehe_valik'      => self::PAGE_SOURCES[$audit->page_source] ?? null,
                'märksõna'        => $audit->keyword,
                'google_positsioon' => $lead?->serp_position,
                'google_leht'     => $lead?->serp_page,
                'konkurendid'     => $lead?->serp_competitors,
                'lehe_pealkiri'   => $facts['title'] ?? null,
                'skoor_0_100'     => $audit->score,
                'leiud'           => array_values($findings),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );

        if (is_string($answer['summary'] ?? null) && trim($answer['summary']) !== '') {
            return trim($answer['summary']);
        }

        // Fallback: plain list, most important first.
        return "Auditi käigus leidsime järgmised parendamist vajavad kohad:\n\n"
            . implode("\n", array_map(fn ($r) => '• Ei ole korras: ' . $r['label'] . ($r['explanation'] ? '. ' . $r['explanation'] : ''), $failed));
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function row(SeoAuditCheck $check, array $r): array
    {
        return [
            'key'         => $check->key,
            'label'       => $check->label,
            'type'        => $check->type,
            'weight'      => $check->weight,
            'explanation' => $check->client_explanation,
            'status'      => $r['status'],
            'value'       => is_scalar($r['value'] ?? null) ? mb_substr((string) $r['value'], 0, 300) : null,
            'note'        => $r['note'] ?? null,
        ];
    }

    private function aiFacts(array $facts): array
    {
        return array_intersect_key($facts, array_flip([
            'title', 'meta_description', 'h1', 'h2', 'word_count',
            'has_tel', 'has_mailto', 'has_form', 'text_excerpt',
        ]));
    }

    private function verdict(bool $pass, mixed $value, ?string $note = null): array
    {
        return ['status' => $pass ? SeoAudit::RESULT_PASS : SeoAudit::RESULT_FAIL, 'value' => $value, 'note' => $note];
    }

    private function skip(string $note): array
    {
        return ['status' => SeoAudit::RESULT_SKIP, 'value' => null, 'note' => $note];
    }

    private function getText(string $url): ?string
    {
        try {
            $r = Http::withOptions(['verify' => false])->timeout(8)->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $r->successful()) {
            return null;
        }
        // Many sites answer every unknown path with their HTML homepage.
        $body = $r->body();
        return stripos(ltrim($body), '<!doctype html') === 0 || stripos(ltrim($body), '<html') === 0 ? null : $body;
    }

    private function origin(string $url): string
    {
        $p = parse_url($url);
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    private function normalizeUrl(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (! preg_match('~^https?://~i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        return filter_var($raw, FILTER_VALIDATE_URL) ?: null;
    }

    private function fail(SeoAudit $audit, string $error): SeoAudit
    {
        Log::info('[SeoAudit] failed', ['audit' => $audit->id, 'error' => $error]);
        $audit->update(['status' => SeoAudit::STATUS_FAILED, 'error' => $error]);
        return $audit;
    }
}
