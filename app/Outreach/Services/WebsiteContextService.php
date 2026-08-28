<?php

namespace App\Outreach\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

/**
 * WebsiteContextService
 *
 * Fetches a lead's homepage and extracts the small set of signals that
 * matter for LLM cold-email drafting:
 *
 *   - title, meta description, primary H1
 *   - a compressed slice of visible text (~1200 chars)
 *   - internal links that look like references / projects / news
 *
 * The output is deliberately tight (~1-1.5k tokens after JSON encode)
 * so it fits comfortably inside the LLM prompt budget the drafter uses.
 *
 * Fetch is defensive: 8s timeout, ignores TLS errors on legacy sites,
 * follows redirects up to 5 hops, sends a browsery User-Agent (some
 * WAFs deny curl/php defaults). On failure returns a partial context
 * with `fetch_error` set so the drafter can decide to bail or write
 * a generic message.
 */
class WebsiteContextService
{
    private const TIMEOUT_SECONDS  = 8;
    private const MAX_TEXT_CHARS   = 1500;
    private const MAX_LINKS        = 8;
    private const REDIRECT_HOPS    = 5;

    /** Path fragments that mark portfolio / references / news pages. */
    private const REF_PATH_HINTS = [
        'referents', 'portfoolio', 'projekt', 'töid', 'toit', 'juhtum',
        'uudised', 'blogi', 'news', 'blog', 'case', 'work', 'client',
        'referen', 'portfolio', 'stori',
    ];

    /**
     * Fetch + parse a URL. Returns:
     *   [
     *     'url'              => canonical URL that was actually fetched (after redirects),
     *     'title'            => <title>,
     *     'meta_description' => <meta name="description">,
     *     'h1'               => first non-empty h1 text,
     *     'text_excerpt'     => cleaned visible text (up to MAX_TEXT_CHARS),
     *     'reference_links'  => [['url' => ..., 'label' => ...], ...] (up to MAX_LINKS),
     *     'sources'          => list of URLs actually consulted,
     *     'fetch_error'      => null | string,
     *   ]
     */
    public function fetch(string $rawUrl): array
    {
        $url = $this->normalizeUrl($rawUrl);
        $result = [
            'url'              => $url,
            'title'            => null,
            'meta_description' => null,
            'h1'               => null,
            'text_excerpt'     => null,
            'reference_links'  => [],
            'sources'          => [],
            'fetch_error'      => null,
        ];

        if ($url === null) {
            $result['fetch_error'] = 'invalid_url';
            return $result;
        }

        try {
            $response = Http::withOptions([
                    'allow_redirects' => ['max' => self::REDIRECT_HOPS],
                    'verify'          => false,
                ])
                ->timeout(self::TIMEOUT_SECONDS)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (compatible; WebfightOutreachBot/1.0; +https://webfight.ee)',
                    'Accept'          => 'text/html,application/xhtml+xml',
                    'Accept-Language' => 'et,en;q=0.7',
                ])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('[WebsiteContext] fetch threw', ['url' => $url, 'err' => $e->getMessage()]);
            $result['fetch_error'] = 'fetch_exception';
            return $result;
        }

        if (! $response->successful()) {
            $result['fetch_error'] = 'http_' . $response->status();
            return $result;
        }

        $finalUrl = (string) ($response->effectiveUri() ?? $url);
        $result['url']        = $finalUrl;
        $result['sources'][]  = $finalUrl;

        $html = (string) $response->body();
        if (trim($html) === '') {
            $result['fetch_error'] = 'empty_body';
            return $result;
        }

        $this->extractFromHtml($html, $finalUrl, $result);

        return $result;
    }

    /** Push extracted signals into $result by reference. */
    private function extractFromHtml(string $html, string $baseUrl, array &$result): void
    {
        // DomCrawler swallows the multi-byte handling that plain DOMDocument
        // gets wrong; prefix ensures UTF-8 stays intact regardless of what
        // charset the page declares.
        $crawler = new Crawler();
        $crawler->addHtmlContent($html, 'UTF-8');

        $result['title']            = $this->firstText($crawler, 'title');
        $result['meta_description'] = $this->attr($crawler, 'meta[name="description"]', 'content')
            ?? $this->attr($crawler, 'meta[property="og:description"]', 'content');
        $result['h1']               = $this->firstText($crawler, 'h1');

        // Kill scripts/styles/noscript before harvesting visible text.
        $textCrawler = new Crawler();
        $textCrawler->addHtmlContent($html, 'UTF-8');
        try {
            $textCrawler->filter('script, style, noscript, template, iframe, header nav, footer')
                ->each(function ($n) { foreach ($n as $node) $node->parentNode?->removeChild($node); });
        } catch (\Throwable $e) {
            // Selectors sometimes throw on malformed HTML; not fatal.
        }
        $rawText = $textCrawler->filter('body')->count() > 0
            ? $textCrawler->filter('body')->text('')
            : $textCrawler->text('');
        $result['text_excerpt'] = $this->compress($rawText, self::MAX_TEXT_CHARS);

        // Reference-y links — narrow to internal, hint-matching paths.
        $host = parse_url($baseUrl, PHP_URL_HOST);
        $links = [];
        try {
            $crawler->filter('a[href]')->each(function ($node) use ($baseUrl, $host, &$links) {
                if (count($links) >= self::MAX_LINKS) return;
                $href = trim((string) $node->attr('href'));
                if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) return;
                $abs  = $this->absolutize($href, $baseUrl);
                if ($abs === null) return;
                if ($host && parse_url($abs, PHP_URL_HOST) !== $host) return;
                $lower = strtolower($abs);
                foreach (self::REF_PATH_HINTS as $hint) {
                    if (str_contains($lower, $hint)) {
                        $label = $this->compress($node->text(''), 80);
                        $links[$abs] = ['url' => $abs, 'label' => $label ?: $hint];
                        break;
                    }
                }
            });
        } catch (\Throwable $e) {
            // ignore selector failures
        }
        $result['reference_links'] = array_values($links);
        $result['sources']         = array_values(array_unique(array_merge(
            $result['sources'],
            array_column($result['reference_links'], 'url'),
        )));
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function normalizeUrl(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') return null;
        if (! preg_match('~^https?://~i', $raw)) {
            $raw = 'https://' . ltrim($raw, '/');
        }
        return filter_var($raw, FILTER_VALIDATE_URL) ?: null;
    }

    private function firstText(Crawler $c, string $selector): ?string
    {
        try {
            $node = $c->filter($selector)->first();
            if ($node->count() === 0) return null;
            $t = trim($node->text(''));
            return $t !== '' ? $this->compress($t, 300) : null;
        } catch (\Throwable $e) { return null; }
    }

    private function attr(Crawler $c, string $selector, string $attr): ?string
    {
        try {
            $node = $c->filter($selector)->first();
            if ($node->count() === 0) return null;
            $v = trim((string) $node->attr($attr));
            return $v !== '' ? $this->compress($v, 500) : null;
        } catch (\Throwable $e) { return null; }
    }

    private function compress(string $text, int $max): string
    {
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);
        if (mb_strlen($text) <= $max) return $text;
        return mb_substr($text, 0, $max) . '…';
    }

    private function absolutize(string $href, string $base): ?string
    {
        if (preg_match('~^https?://~i', $href)) return $href;
        $parts = parse_url($base);
        if (! $parts || ! isset($parts['host'])) return null;
        $scheme = $parts['scheme'] ?? 'https';
        $root   = $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($href, '/')) return $root . $href;
        $path = rtrim(dirname($parts['path'] ?? '/'), '/');
        return $root . $path . '/' . $href;
    }
}
