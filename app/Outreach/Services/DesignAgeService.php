<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * DesignAgeService
 *
 * Estimates how old a lead's website design is by comparing the site's main
 * CSS file across historical Wayback Machine snapshots.
 *
 * ── Why CSS content, not just the digest ────────────────────────────────────
 * The Wayback CDX API exposes a SHA1 `digest` per snapshot, so `collapse=digest`
 * cheaply lists the points where a file changed. But a digest is binary: a
 * one-character edit (updated copyright year, a cache-buster comment, a colour
 * tweak) produces a brand-new digest even though the design is unchanged.
 * Relying on the digest alone would falsely reset the design age to "recent".
 *
 * So we download the actual CSS content at each distinct-digest snapshot and
 * measure content SIMILARITY against today's version. Two versions that are,
 * say, 97% identical are treated as the same design; a redesign shows up as a
 * sharp similarity drop. The design age is how far back we can walk while
 * similarity stays at or above the threshold.
 *
 * Stored fields:
 *   lead.design_year       — year the current design was established (e.g. 2018)
 *   lead.design_age        — age in years (currentYear - design_year, e.g. 8)
 *   lead.design_similarity — similarity of the boundary snapshot vs today (0-100)
 *
 * Template placeholders:
 *   {{design_year}}        — e.g. "2018"
 *   {{design_age}}         — e.g. "8"
 *
 * Config:
 *   services.design_age.threshold (env DESIGN_AGE_SIMILARITY_THRESHOLD, default 85)
 *     — minimum content similarity (%) for two CSS versions to count as the
 *       same design.
 *
 * No API key is required — the Wayback Machine CDX API and raw-archive endpoint
 * are public.
 */
class DesignAgeService
{
    private const CDX_URL      = 'http://web.archive.org/cdx/search/cdx';
    private const WAYBACK_RAW  = 'http://web.archive.org/web/';
    private const CDX_TIMEOUT  = 30;   // CDX can be slow on large domains
    private const HTTP_TIMEOUT = 20;   // fetching a single CSS / homepage

    private const USER_AGENT = 'Mozilla/5.0 (compatible; DesignAgeBot/1.0; +webfight.ee)';

    /**
     * Retries per archive.org request on 429 / 503 / timeout before giving up.
     * The Wayback Machine rate-limits aggressively; without backoff a burst of
     * requests silently degrades into false "unknown" results.
     */
    private const MAX_RETRIES = 3;

    /** How many CSS candidates to try before giving up on a lead. */
    private const MAX_CSS_ATTEMPTS = 3;

    /** Never download more than this many historical versions per lead. */
    private const MAX_DOWNLOADS = 8;

    /** Wall-clock time of the last archive.org request (for inter-request pacing). */
    private float $lastRequestAt = 0.0;

    /** Never look further back than this many years. */
    private const MAX_YEARS_BACK = 12;

    /**
     * The tracked CSS's most recent snapshot must be within this many years,
     * otherwise the file is treated as abandoned (not the current design) and
     * rejected. A genuinely old design that is still served keeps getting
     * re-crawled, so its newest snapshot stays recent even when its content is
     * years old — only truly dead files fail this check.
     */
    private const MAX_CURRENT_SNAPSHOT_AGE = 3;

    /** Minimum bytes for a CSS body to be considered usable. */
    private const MIN_CSS_BYTES = 200;

    // Library / vendor / CDN / back-office stylesheets that are not the site's
    // own public-facing design.
    private const CSS_IGNORE = [
        'jquery', 'bootstrap', 'font-awesome', 'fontawesome', 'fonts.googleapis',
        'slick', 'swiper', 'owl', 'animate', 'lightbox', 'fancybox', 'magnific',
        'cdn-cgi', 'cookie', 'gtag', 'gstatic', 'cloudflare', 'plugin', 'wp-includes',
        'select2', 'datatables', 'flickity', 'aos.css', 'normalize', 'reset.css',
        'wp-admin', '/admin', 'admin.css', 'admin-', 'editor', 'gutenberg',
        'dashboard', 'login', 'elementor', 'woocommerce',
    ];

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Measure a single lead's design age and save the results.
     *
     * @return array{design_year:int, design_age:int, similarity:int, css_url:string}|null
     */
    public function measure(OutreachLead $lead): ?array
    {
        $site = $this->normalizeUrl($lead->website);

        if (! $site) {
            return null;
        }

        try {
            // Try the ranked CSS candidates in turn: the first one that has
            // usable Wayback history wins. This tolerates a top pick that turns
            // out to be un-archived (e.g. a per-deploy hashed file).
            $candidates = $this->resolveCssCandidates($site);
            $result     = null;
            $attempts   = 0;

            foreach ($candidates as $cssUrl) {
                if ($attempts >= self::MAX_CSS_ATTEMPTS) {
                    break;
                }
                $attempts++;

                $result = $this->analyzeCss($cssUrl);
                if ($result) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[DesignAge] Measurement failed', [
                'lead_id' => $lead->id,
                'site'    => $site,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }

        if (! $result) {
            Log::info('[DesignAge] No trackable CSS history found', [
                'lead_id' => $lead->id,
                'site'    => $site,
            ]);
            return null;
        }

        $lead->update([
            'design_year'       => $result['design_year'],
            'design_age'        => $result['design_age'],
            'design_similarity' => $result['similarity'],
        ]);

        return $result;
    }

    // ── CSS resolution ────────────────────────────────────────────────────────

    /**
     * Build a ranked list of candidate design stylesheets for a site.
     *
     * Combines two sources:
     *   - Archived CSS files (Wayback CDX) — these are guaranteed to have
     *     history, so they are listed first.
     *   - The live homepage's <link rel=stylesheet> — reflects the current
     *     design, useful when the archive listing is thin.
     *
     * The caller tries them in order until one yields usable history.
     *
     * @return array<int, string>  absolute CSS URLs, best-first
     */
    private function resolveCssCandidates(string $site): array
    {
        $host = parse_url($site, PHP_URL_HOST) ?: '';

        // Archived CSS first (definitely trackable).
        $archived = $this->discoverArchivedCss($host);

        // Live homepage CSS (current design).
        $live = [];
        try {
            $resp = Http::timeout(self::HTTP_TIMEOUT)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DesignAgeBot/1.0)'])
                ->get($site);

            if ($resp->successful()) {
                $live = $this->extractCssLinks($resp->body(), $site);
            }
        } catch (\Throwable $e) {
            // live page optional — archive candidates may still carry us
        }

        return $this->rankCss(array_merge($archived, $live), $host);
    }

    /**
     * Extract absolute stylesheet URLs from an HTML document.
     *
     * @return array<int, string>
     */
    private function extractCssLinks(string $html, string $baseUrl): array
    {
        if (! preg_match_all('#<link\b[^>]*>#i', $html, $tags)) {
            return [];
        }

        $links = [];
        foreach ($tags[0] as $tag) {
            if (! preg_match('#rel\s*=\s*["\']?[^"\'>]*stylesheet#i', $tag)) {
                continue;
            }
            if (! preg_match('#href\s*=\s*["\']([^"\']+)["\']#i', $tag, $m)) {
                continue;
            }
            $abs = $this->absoluteUrl(trim($m[1]), $baseUrl);
            if ($abs) {
                $links[] = $abs;
            }
        }

        return $links;
    }

    /**
     * List archived CSS files for a domain (guaranteed to have Wayback history).
     *
     * @return array<int, string>
     */
    private function discoverArchivedCss(string $host): array
    {
        if ($host === '') {
            return [];
        }

        $resp = $this->archiveGet(self::CDX_URL, [
            'url'       => $host,
            'matchType' => 'domain',
            'output'    => 'json',
            'filter'    => 'mimetype:text/css',
            'collapse'  => 'urlkey',
            'limit'     => 80,
            'fl'        => 'original',
        ], self::CDX_TIMEOUT);

        if (! $resp || ! $resp->successful()) {
            return [];
        }

        $rows = $resp->json();
        if (! is_array($rows) || count($rows) < 2) {
            return [];
        }
        array_shift($rows); // header row

        $urls = [];
        foreach ($rows as $row) {
            $url = is_array($row) ? ($row[0] ?? null) : $row;
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Filter and rank candidate stylesheet URLs, best design candidate first.
     *
     * Drops library / back-office / content-hashed stylesheets, then scores the
     * rest by how much they look like a site's primary theme file. Preserves
     * input order on ties (archived candidates are passed in first), and
     * de-duplicates.
     *
     * @param  array<int, string> $candidates
     * @return array<int, string>
     */
    private function rankCss(array $candidates, string $host): array
    {
        $ranked = [];
        $seen   = [];
        $order  = 0;

        foreach ($candidates as $url) {
            if (isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;

            $lower = strtolower($url);

            if (! str_contains($lower, '.css')) {
                continue;
            }
            foreach (self::CSS_IGNORE as $bad) {
                if (str_contains($lower, $bad)) {
                    continue 2;
                }
            }
            // Skip content-hashed filenames (8+ hex chars bracketed by . - _)
            if (preg_match('#[._-][0-9a-f]{8,}\.(?:min\.)?css#i', $lower)) {
                continue;
            }

            $sameHost = $host !== '' && str_contains($lower, strtolower($host));
            $score = 0;
            $score += $sameHost ? 100 : 0;
            // Prefer names that look like the primary theme stylesheet
            if (preg_match('#/(style|styles|main|theme|site|app|screen|global|output)\.(?:min\.)?css#i', $lower)) {
                $score += 50;
            }
            // A CSS living under the active theme folder is very likely the design
            if (preg_match('#/(themes?|template|templates)/#i', $lower)) {
                $score += 30;
            }
            $score -= substr_count($lower, '/'); // shallower paths slightly preferred

            $ranked[] = ['url' => $url, 'score' => $score, 'order' => $order++];
        }

        // Highest score first; stable on ties via original order.
        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score'] ?: $a['order'] <=> $b['order']);

        return array_map(fn ($r) => $r['url'], $ranked);
    }

    // ── History analysis ──────────────────────────────────────────────────────

    /**
     * Walk a CSS file's history backwards, merging near-identical versions, to
     * find when the current design was established.
     *
     * @return array{design_year:int, design_age:int, similarity:int, css_url:string}|null
     */
    private function analyzeCss(string $cssUrl): ?array
    {
        // Look the file up by its exact URL. If the archive has no history for
        // it, retry without the query string — cache-buster params (?ver=3.0,
        // ?v=<hash>) change on every deploy and fragment the history, while the
        // underlying path is stable.
        $lookupUrl = $cssUrl;
        $rows      = $this->cdxDigests($lookupUrl); // chronological: oldest → newest

        if (empty($rows)) {
            $stripped = $this->stripQuery($cssUrl);
            if ($stripped !== $cssUrl) {
                $lookupUrl = $stripped;
                $rows      = $this->cdxDigests($lookupUrl);
            }
        }

        if (empty($rows)) {
            return null;
        }

        // Newest first — the most recent capture is our "today" reference.
        $rows = array_reverse($rows);

        $currentTs   = $rows[0][0];
        $currentYear = (int) date('Y');

        // Reject abandoned files: if even the newest snapshot is years old, this
        // CSS is no longer served and is not the site's current design.
        if ($currentYear - (int) substr($currentTs, 0, 4) > self::MAX_CURRENT_SNAPSHOT_AGE) {
            return null;
        }

        $currentContent = $this->fetchArchived($currentTs, $lookupUrl);

        if ($currentContent === null || strlen($currentContent) < self::MIN_CSS_BYTES) {
            return null;
        }

        $threshold = $this->threshold();

        // Assume, until proven otherwise, that the design dates to the newest snapshot.
        $boundaryTs  = $currentTs;
        $boundarySim = 100;

        $downloads = 0;

        foreach (array_slice($rows, 1) as [$ts, $digest]) {
            $year = (int) substr($ts, 0, 4);

            if ($currentYear - $year > self::MAX_YEARS_BACK) {
                break;
            }
            if ($downloads >= self::MAX_DOWNLOADS) {
                break;
            }

            $content = $this->fetchArchived($ts, $lookupUrl);
            $downloads++;

            if ($content === null || strlen($content) < self::MIN_CSS_BYTES) {
                continue;
            }

            $sim = $this->similarity($currentContent, $content);

            if ($sim >= $threshold) {
                // Still the same design — push the boundary further back.
                $boundaryTs  = $ts;
                $boundarySim = $sim;
            } else {
                // A materially different (older) design — stop here.
                break;
            }
        }

        $designYear = (int) substr($boundaryTs, 0, 4);

        return [
            'design_year' => $designYear,
            'design_age'  => max(0, $currentYear - $designYear),
            'similarity'  => $boundarySim,
            'css_url'     => $lookupUrl,
        ];
    }

    /**
     * Drop the query string from a URL (keeps scheme/host/path).
     */
    private function stripQuery(string $url): string
    {
        $pos = strpos($url, '?');

        return $pos === false ? $url : substr($url, 0, $pos);
    }

    /**
     * Distinct-content snapshots of a URL, oldest → newest.
     *
     * `collapse=digest` returns the first capture of each run of identical
     * digests, so the result is already deduplicated to change-points.
     *
     * @return array<int, array{0:string,1:string}>  rows of [timestamp, digest]
     */
    private function cdxDigests(string $url): array
    {
        $resp = $this->archiveGet(self::CDX_URL, [
            'url'      => $url,
            'output'   => 'json',
            'collapse' => 'digest',
            'filter'   => 'statuscode:200',
            'fl'       => 'timestamp,digest',
        ], self::CDX_TIMEOUT);

        if (! $resp || ! $resp->successful()) {
            return [];
        }

        $rows = $resp->json();
        if (! is_array($rows) || count($rows) < 2) {
            return [];
        }
        array_shift($rows); // header row

        return array_values(array_filter($rows, fn ($r) => is_array($r) && isset($r[0], $r[1])));
    }

    /**
     * Download the raw archived body of a URL at a given snapshot.
     *
     * The `id_` modifier returns the original bytes without the Wayback
     * toolbar/rewriting, so we compare real site content.
     */
    private function fetchArchived(string $timestamp, string $url): ?string
    {
        $resp = $this->archiveGet(self::WAYBACK_RAW . $timestamp . 'id_/' . $url, [], self::HTTP_TIMEOUT);

        return ($resp && $resp->successful()) ? $resp->body() : null;
    }

    // ── Rate-limited archive.org access ───────────────────────────────────────

    /**
     * Perform a GET against archive.org with polite pacing and retry/backoff.
     *
     * The Wayback Machine throttles hard: it answers 429/503 (sometimes with a
     * Retry-After header) or simply stalls when hit too fast. We therefore
     * space requests out and, on a throttle/timeout, back off and retry rather
     * than silently reporting "no history" — which would otherwise turn a
     * temporary block into a batch of false "unknown" results.
     *
     * Returns the response, or null if it never succeeded.
     */
    private function archiveGet(string $url, array $query = [], int $timeout = self::CDX_TIMEOUT): ?\Illuminate\Http\Client\Response
    {
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $this->throttle();

            try {
                $resp = Http::timeout($timeout)
                    ->withHeaders(['User-Agent' => self::USER_AGENT])
                    ->get($url, $query);
            } catch (\Throwable $e) {
                // Timeout / connection error — back off and retry.
                if ($attempt >= self::MAX_RETRIES) {
                    return null;
                }
                $this->backoff($attempt);
                continue;
            }

            // Explicit throttle / temporary unavailability.
            if (in_array($resp->status(), [429, 503], true)) {
                if ($attempt >= self::MAX_RETRIES) {
                    return null;
                }
                $this->backoff($attempt, (int) $resp->header('Retry-After'));
                continue;
            }

            return $resp;
        }

        return null;
    }

    /**
     * Enforce a minimum gap between consecutive archive.org requests.
     */
    private function throttle(): void
    {
        $minGapMs = max(0, (int) config('services.design_age.request_delay_ms', 1500));

        if ($this->lastRequestAt > 0.0) {
            $elapsedMs = (microtime(true) - $this->lastRequestAt) * 1000;
            if ($elapsedMs < $minGapMs) {
                usleep((int) (($minGapMs - $elapsedMs) * 1000));
            }
        }

        $this->lastRequestAt = microtime(true);
    }

    /**
     * Sleep after a throttle/timeout: honour Retry-After when given, otherwise
     * exponential backoff (2s, 4s, 8s…), capped.
     */
    private function backoff(int $attempt, int $retryAfter = 0): void
    {
        $seconds = $retryAfter > 0
            ? min($retryAfter, 60)
            : min(2 ** $attempt, 30);

        sleep($seconds);
        $this->lastRequestAt = microtime(true);
    }

    // ── Similarity ────────────────────────────────────────────────────────────

    /**
     * Content similarity of two CSS documents, 0-100.
     *
     * Uses a Jaccard index over normalised rule/declaration tokens rather than
     * a raw character diff: this ignores whitespace, comments, and rule
     * reordering (all common between builds) and focuses on whether the same
     * selectors and declarations are present.
     */
    private function similarity(string $a, string $b): int
    {
        $ta = $this->cssTokens($a);
        $tb = $this->cssTokens($b);

        if (empty($ta) && empty($tb)) {
            return 100;
        }
        if (empty($ta) || empty($tb)) {
            return 0;
        }

        $intersection = count(array_intersect_key($ta, $tb));
        $union        = count($ta + $tb);

        return $union > 0 ? (int) round($intersection / $union * 100) : 0;
    }

    /**
     * Break CSS into a set of normalised tokens (selectors + declarations).
     *
     * Returned as an associative array (token => true) so set operations can
     * use the fast array_intersect_key / array union by key.
     *
     * @return array<string, bool>
     */
    private function cssTokens(string $css): array
    {
        // Strip comments, then collapse whitespace.
        $css = preg_replace('#/\*.*?\*/#s', ' ', $css) ?? $css;
        $css = strtolower($css);
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;

        // Split on rule/declaration boundaries.
        $parts = preg_split('/[{};]+/', $css) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (strlen($part) > 2) {
                $tokens[$part] = true;
            }
        }

        return $tokens;
    }

    // ── URL helpers ───────────────────────────────────────────────────────────

    private function threshold(): int
    {
        $t = (int) config('services.design_age.threshold', 85);

        return max(1, min(100, $t));
    }

    /**
     * Ensure a website URL has a scheme. Returns null if unusable.
     */
    private function normalizeUrl(?string $website): ?string
    {
        if (empty($website)) {
            return null;
        }

        $website = trim($website);

        if (! preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }

        return filter_var($website, FILTER_VALIDATE_URL) ? $website : null;
    }

    /**
     * Resolve a possibly-relative URL against a base document URL.
     */
    private function absoluteUrl(string $url, string $baseUrl): ?string
    {
        if ($url === '' || str_starts_with($url, 'data:')) {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        $parts = parse_url($baseUrl);
        if (! $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];

        if (str_starts_with($url, '/')) {
            return $origin . $url;
        }

        return $origin . '/' . ltrim($url, './');
    }
}
