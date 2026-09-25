<?php

namespace App\Seo\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Finds the page on a site that targets a keyword ("katusetööd tartu" →
 * firma.ee/teenused/katusetood).
 *
 *   1. Candidates = sitemap URLs (robots.txt "Sitemap:", /sitemap.xml,
 *      /sitemap_index.xml, /wp-sitemap.xml, one level of sitemap index) plus
 *      the homepage's internal links with their anchor text.
 *   2. Each candidate is scored by keyword coverage of its URL slug + anchor.
 *   3. The best few are fetched and scored again on <title> and <h1>.
 *
 * Returns the best page, or the homepage if it clearly targets the keyword
 * itself (small one-service sites), or nothing.
 */
class LandingPageFinder
{
    private const TIMEOUT        = 8;
    private const MAX_URLS       = 1500;
    private const MAX_CHILD_MAPS = 5;
    private const FETCH_TOP      = 5;

    /** Per-instance cache so several keywords on one site fetch it once. */
    private array $candidateCache = [];
    private array $homeCache = [];

    /**
     * @return array{url:?string, source:string, note:string}
     *   source: found (a subpage) | home (homepage targets it) | none
     */
    public function find(string $siteUrl, string $keyword): array
    {
        $origin = $this->origin($siteUrl);
        if ($origin === null || trim($keyword) === '') {
            return ['url' => null, 'source' => 'none', 'note' => 'Aadress või märksõna puudub.'];
        }

        $home = $this->home($origin);
        $candidates = $this->candidates($origin, $home['html']);

        // Stage 1: cheap score from URL slug + link text.
        $scored = [];
        foreach ($candidates as $url => $anchor) {
            $path = urldecode((string) parse_url($url, PHP_URL_PATH));
            if (trim($path, '/') === '') {
                continue;
            }
            $slug = preg_replace('/[-_\/.]+/', ' ', $path);
            $cov  = max(
                PageAnalyzer::keywordCoverage($slug, $keyword),
                PageAnalyzer::keywordCoverage($anchor, $keyword),
            );
            if ($cov >= 0.5) {
                $scored[$url] = ['url_cov' => $cov, 'depth' => substr_count(trim($path, '/'), '/')];
            }
        }

        uasort($scored, fn ($a, $b) => [$b['url_cov'], $a['depth']] <=> [$a['url_cov'], $b['depth']]);

        // Stage 2: fetch the top few and look at title + H1.
        $best = null;
        foreach (array_slice($scored, 0, self::FETCH_TOP, true) as $url => $s) {
            $html = $this->get($url);
            if ($html === null) {
                continue;
            }
            [$title, $h1] = $this->titleAndH1($html);
            $pageCov = max(
                PageAnalyzer::keywordCoverage($title, $keyword),
                PageAnalyzer::keywordCoverage($h1, $keyword),
            );
            $score = $pageCov + 0.5 * $s['url_cov'];
            if (($pageCov >= 0.5 || $s['url_cov'] >= 1.0) && ($best === null || $score > $best['score'])) {
                $best = ['url' => $url, 'score' => $score, 'title' => $title ?: $h1];
            }
        }

        if ($best) {
            return [
                'url'    => $best['url'],
                'source' => 'found',
                'note'   => 'Leitud ' . count($candidates) . ' lehe hulgast' . ($best['title'] ? ': „' . mb_substr($best['title'], 0, 120) . '“' : ''),
            ];
        }

        if ($home['html'] !== null) {
            [$title, $h1] = $this->titleAndH1($home['html']);
            if (PageAnalyzer::containsKeyword($title, $keyword) || PageAnalyzer::containsKeyword($h1, $keyword)) {
                return ['url' => $home['url'], 'source' => 'home', 'note' => 'Eraldi alamlehte pole, aga avaleht on märksõnale suunatud.'];
            }
        }

        return [
            'url'    => null,
            'source' => 'none',
            'note'   => $candidates
                ? 'Märksõnale vastavat lehte ei leitud (' . count($candidates) . ' lehte vaadatud).'
                : 'Lehtede nimekirja (sitemap, menüü) ei õnnestunud lugeda.',
        ];
    }

    // ─── candidates ─────────────────────────────────────────────────────────

    /** @return array{url:string, html:?string} */
    private function home(string $origin): array
    {
        return $this->homeCache[$origin] ??= ['url' => $origin . '/', 'html' => $this->get($origin . '/')];
    }

    /** @return array<string, string> url => anchor text */
    private function candidates(string $origin, ?string $homeHtml): array
    {
        if (isset($this->candidateCache[$origin])) {
            return $this->candidateCache[$origin];
        }

        $host = $this->host($origin);
        $out  = [];

        foreach ($this->sitemapUrls($origin) as $u) {
            if ($this->sameHost($u, $host) && $this->isPage($u)) {
                $out[$this->normalize($u)] = '';
            }
            if (count($out) >= self::MAX_URLS) {
                break;
            }
        }

        if ($homeHtml !== null) {
            try {
                $c = new Crawler($homeHtml, $origin . '/');
                foreach ($c->filter('a[href]')->links() as $link) {
                    $u = $link->getUri();
                    if (! $this->sameHost($u, $host) || ! $this->isPage($u)) {
                        continue;
                    }
                    $u = $this->normalize($u);
                    $anchor = trim(preg_replace('/\s+/u', ' ', $link->getNode()->textContent) ?? '');
                    $out[$u] = trim(($out[$u] ?? '') . ' ' . $anchor);
                }
            } catch (\Throwable) {
            }
        }

        return $this->candidateCache[$origin] = $out;
    }

    /** @return string[] */
    private function sitemapUrls(string $origin): array
    {
        $maps = [];
        if (preg_match_all('/^\s*sitemap:\s*(\S+)/im', (string) $this->get($origin . '/robots.txt', false), $m)) {
            $maps = $m[1];
        }
        $maps = $maps ?: [$origin . '/sitemap.xml', $origin . '/sitemap_index.xml', $origin . '/wp-sitemap.xml'];

        foreach ($maps as $map) {
            $xml = $this->get($map, false);
            if ($xml === null || ! str_contains($xml, '<loc')) {
                continue;
            }
            $locs = $this->locs($xml);

            if (stripos($xml, '<sitemapindex') === false) {
                return $locs;
            }

            // Index: pages/services first, skip posts/tags/products when possible.
            usort($locs, fn ($a, $b) => $this->childRank($a) <=> $this->childRank($b));
            $urls = [];
            foreach (array_slice($locs, 0, self::MAX_CHILD_MAPS) as $child) {
                $urls = array_merge($urls, $this->locs((string) $this->get($child, false)));
                if (count($urls) >= self::MAX_URLS) {
                    break;
                }
            }

            return $urls;
        }

        return [];
    }

    private function childRank(string $url): int
    {
        $u = strtolower($url);
        return match (true) {
            str_contains($u, 'page')                                  => 0,
            str_contains($u, 'teenus') || str_contains($u, 'service') => 0,
            str_contains($u, 'post') || str_contains($u, 'product')   => 2,
            str_contains($u, 'tag') || str_contains($u, 'categor') || str_contains($u, 'author') => 3,
            default                                                   => 1,
        };
    }

    /** @return string[] */
    private function locs(string $xml): array
    {
        preg_match_all('~<loc>\s*(?:<!\[CDATA\[)?\s*(.+?)\s*(?:\]\]>)?\s*</loc>~is', $xml, $m);

        return array_map(fn ($u) => html_entity_decode(trim($u)), $m[1] ?? []);
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    /** @return array{0:?string, 1:?string} */
    private function titleAndH1(string $html): array
    {
        $pick = function (string $tag) use ($html): ?string {
            if (! preg_match("~<{$tag}\b[^>]*>(.*?)</{$tag}>~is", $html, $m)) {
                return null;
            }
            $t = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[1]))) ?? '');
            return $t !== '' ? $t : null;
        };

        return [$pick('title'), $pick('h1')];
    }

    /** @param bool $html true = expect an HTML page, false = any text body */
    private function get(string $url, bool $html = true): ?string
    {
        try {
            $r = Http::withOptions(['allow_redirects' => ['max' => 5], 'verify' => false])
                ->timeout(self::TIMEOUT)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; WebfightSeoAudit/1.0; +https://webfight.ee)'])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $r->successful()) {
            return null;
        }

        $body = $r->body();
        $start = strtolower(ltrim(substr($body, 0, 2000)));
        $looksHtml = str_starts_with($start, '<!doctype html') || str_starts_with($start, '<html') || str_contains($start, '<head');

        // Many sites answer unknown paths (/sitemap.xml, /robots.txt) with the homepage.
        return $looksHtml === $html ? $body : null;
    }

    private function origin(string $url): ?string
    {
        $url = trim($url);
        if (! preg_match('~^https?://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        $p = parse_url($url);
        if (empty($p['host'])) {
            return null;
        }

        return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    private function host(string $url): string
    {
        return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    private function sameHost(string $url, string $host): bool
    {
        return $this->host($url) === $host;
    }

    private function isPage(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return ! preg_match('/\.(pdf|jpe?g|png|gif|webp|svg|zip|docx?|xlsx?|mp4|xml)$/', $path)
            && ! preg_match('~/(wp-admin|wp-content|wp-json|cart|checkout|ostukorv|konto|my-account|login)(/|$)~', $path);
    }

    /** Dedupe key: "/teenused/" and "/teenused#hind" are the same page. */
    private function normalize(string $url): string
    {
        return rtrim(preg_replace('/#.*$/', '', $url), '/');
    }
}
