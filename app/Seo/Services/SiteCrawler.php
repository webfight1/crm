<?php

namespace App\Seo\Services;

use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Shared, cached read access to one client site for the SEO pipeline:
 * homepage HTML, sitemap URLs, homepage links. Bound as a scoped singleton
 * (AppServiceProvider), so the page finder, site type detector and e-shop
 * analyzer fetch each resource once per job / request.
 */
class SiteCrawler
{
    private const TIMEOUT        = 8;
    private const MAX_URLS       = 1500;
    private const MAX_CHILD_MAPS = 6;

    private array $cache = [];

    /** HTML page, or null (error / not HTML). */
    public function page(string $url): ?string
    {
        return $this->memo('page:' . $url, fn () => $this->get($url, true));
    }

    /** Non-HTML text (robots.txt, XML), or null. */
    public function text(string $url): ?string
    {
        return $this->memo('text:' . $url, fn () => $this->get($url, false));
    }

    public function home(string $origin): ?string
    {
        return $this->page($origin . '/');
    }

    /** @return string[] same-host page URLs from the sitemap(s) */
    public function sitemapUrls(string $origin): array
    {
        return $this->memo('sitemap:' . $origin, fn () => $this->readSitemaps($origin));
    }

    /**
     * Sitemap file names (for an index) — "product-sitemap.xml" alone says
     * a lot about the site.
     *
     * @return string[]
     */
    public function sitemapFiles(string $origin): array
    {
        $this->sitemapUrls($origin);

        return $this->cache['sitemapfiles:' . $origin] ?? [];
    }

    /**
     * Internal links of a page with their anchor text.
     *
     * @return array<int, array{url:string, text:string, in_nav:bool}>
     */
    public function links(string $pageUrl, ?string $html): array
    {
        if ($html === null) {
            return [];
        }
        $host = self::host($pageUrl);
        $out  = [];
        try {
            $c = new Crawler($html, $pageUrl);
            foreach ($c->filter('a[href]') as $node) {
                $link = new \Symfony\Component\DomCrawler\Link($node, $pageUrl);
                $u = $link->getUri();
                if (self::host($u) !== $host || ! self::isPage($u)) {
                    continue;
                }
                $inNav = false;
                for ($p = $node->parentNode; $p instanceof \DOMElement; $p = $p->parentNode) {
                    if (in_array(strtolower($p->nodeName), ['nav', 'header'], true)
                        || preg_match('/\b(menu|nav)\b/i', (string) $p->getAttribute('class'))) {
                        $inNav = true;
                        break;
                    }
                }
                $out[] = [
                    'url'    => self::normalize($u),
                    'text'   => trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? ''),
                    'in_nav' => $inNav,
                ];
            }
        } catch (\Throwable) {
        }

        return $out;
    }

    // ─── URL helpers ────────────────────────────────────────────────────────

    public static function origin(string $url): ?string
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

    public static function host(string $url): string
    {
        if ($url !== '' && ! preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    public static function isPage(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return ! preg_match('/\.(pdf|jpe?g|png|gif|webp|svg|zip|docx?|xlsx?|mp4|xml)$/', $path)
            && ! preg_match('~/(wp-admin|wp-content|wp-json|cart|checkout|ostukorv|konto|my-account|login)(/|$)~', $path);
    }

    /** Dedupe key: "/teenused/" and "/teenused#hind" are the same page. */
    public static function normalize(string $url): string
    {
        return rtrim(preg_replace('/#.*$/', '', $url), '/');
    }

    // ─── internals ──────────────────────────────────────────────────────────

    /** Cache that also remembers null (a failed fetch is not retried). */
    private function memo(string $key, callable $fn): mixed
    {
        if (! array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $fn();
        }

        return $this->cache[$key];
    }

    /** @return string[] */
    private function readSitemaps(string $origin): array
    {
        $maps = [];
        if (preg_match_all('/^\s*sitemap:\s*(\S+)/im', (string) $this->text($origin . '/robots.txt'), $m)) {
            $maps = $m[1];
        }
        $maps = $maps ?: [$origin . '/sitemap.xml', $origin . '/sitemap_index.xml', $origin . '/wp-sitemap.xml'];

        foreach ($maps as $map) {
            $xml = $this->text($map);
            if ($xml === null || ! str_contains($xml, '<loc')) {
                continue;
            }
            $locs = $this->locs($xml);

            if (stripos($xml, '<sitemapindex') === false) {
                $this->cache['sitemapfiles:' . $origin] = [$map];
                return $this->ownPages($locs, $origin);
            }

            // Index: pages, services and product categories first; blog tags last.
            $this->cache['sitemapfiles:' . $origin] = $locs;
            usort($locs, fn ($a, $b) => $this->childRank($a) <=> $this->childRank($b));
            $urls = [];
            foreach (array_slice($locs, 0, self::MAX_CHILD_MAPS) as $child) {
                $urls = array_merge($urls, $this->locs((string) $this->text($child)));
                if (count($urls) >= self::MAX_URLS) {
                    break;
                }
            }

            return $this->ownPages($urls, $origin);
        }

        return [];
    }

    /** @return string[] */
    private function ownPages(array $urls, string $origin): array
    {
        $host = self::host($origin);
        $out = [];
        foreach ($urls as $u) {
            if (self::host($u) === $host && self::isPage($u)) {
                $out[self::normalize($u)] = true;
            }
            if (count($out) >= self::MAX_URLS) {
                break;
            }
        }

        return array_keys($out);
    }

    private function childRank(string $url): int
    {
        $u = strtolower($url);
        return match (true) {
            str_contains($u, 'page'), str_contains($u, 'teenus'), str_contains($u, 'service'),
            str_contains($u, 'product_cat'), str_contains($u, 'product-cat'),
            str_contains($u, 'kategoor'), str_contains($u, 'collection')    => 0,
            str_contains($u, 'tag'), str_contains($u, 'author')             => 3,
            str_contains($u, 'post'), str_contains($u, 'product')           => 2,
            default                                                         => 1,
        };
    }

    /** @return string[] */
    private function locs(string $xml): array
    {
        preg_match_all('~<loc>\s*(?:<!\[CDATA\[)?\s*(.+?)\s*(?:\]\]>)?\s*</loc>~is', $xml, $m);

        return array_map(fn ($u) => html_entity_decode(trim($u)), $m[1] ?? []);
    }

    private function get(string $url, bool $html): ?string
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
}
