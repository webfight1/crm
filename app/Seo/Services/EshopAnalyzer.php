<?php

namespace App\Seo\Services;

use App\Seo\Models\SeoAudit;
use App\Seo\Playbook;
use Illuminate\Support\Facades\Http;

/**
 * E-shop-only audit checks.
 *
 *   categoryCheck() — are category names worded the way people search?
 *       categories: product-category URLs (sitemap + homepage links; name =
 *       link text or slug), else the main menu. For each, Google autocomplete
 *       (et/EE) shows real search phrasing; the AI compares and suggests a
 *       better name. Without AI: a name that Google autocompletes counts as OK.
 *   productSchemaCheck() — does a product page carry schema.org/Product?
 */
class EshopAnalyzer
{
    private const CATEGORY_PATH = '~/(product-category|tootekategooria|kategooria|kategooriad|collections|category)/([^/?#]+)/?$~i';
    private const PRODUCT_PATH  = '~/(product|products|toode|tooted)/[^/?#]+/?$~i';
    private const NOT_CATEGORY  = '/^(avaleht|esileht|kontakt|kontaktid|meist|blogi|uudised|ostukorv|konto|minu konto|logi sisse|kkk|tingimused|müügitingimused|tarne|tarneinfo|privaatsus|home|contact|about|blog|cart|account|login|faq)$/iu';

    public function __construct(
        private readonly SiteCrawler $site,
        private readonly SeoAi $ai,
    ) {}

    /** @return array{0: array, 1: array} [check result, categories for seo_audits.extras] */
    public function categoryCheck(string $siteUrl, ?string $keyword): array
    {
        $origin = SiteCrawler::origin($siteUrl);
        $cats = $origin ? $this->categories($origin) : [];
        if (! $cats) {
            return [$this->skip('Kategooriaid ei leitud (sitemap ja menüü).'), []];
        }

        foreach ($cats as &$c) {
            $c['suggestions'] = $this->suggest($c['name']);
        }
        unset($c);

        $judged = $this->judge($cats, $keyword);

        $rated = array_filter($judged, fn ($c) => $c['ok'] !== null);
        $ok    = array_filter($rated, fn ($c) => $c['ok']);
        if (! $rated) {
            return [$this->skip('Kategooriaid ei õnnestunud hinnata.'), $judged];
        }
        $pct = (int) round(count($ok) / count($rated) * 100);

        $rename = array_map(
            fn ($c) => $c['name'] . ($c['better'] ? ' → ' . $c['better'] : ''),
            array_filter($rated, fn ($c) => ! $c['ok'])
        );

        return [[
            'status' => $pct >= Playbook::int('eshop.category_match_pct') ? SeoAudit::RESULT_PASS : SeoAudit::RESULT_FAIL,
            'value'  => count($ok) . '/' . count($rated) . " kategooriat vastab otsingutele ({$pct}%)",
            'note'   => $rename ? 'Ümber sõnastada: ' . implode('; ', array_slice($rename, 0, 8)) : null,
        ], $judged];
    }

    public function productSchemaCheck(string $siteUrl): array
    {
        $origin = SiteCrawler::origin($siteUrl);
        if (! $origin) {
            return $this->skip('Vigane aadress.');
        }

        $candidates = array_merge(
            $this->site->sitemapUrls($origin),
            array_column($this->site->links($origin . '/', $this->site->home($origin)), 'url'),
        );
        $product = null;
        foreach ($candidates as $u) {
            if (preg_match(self::PRODUCT_PATH, (string) parse_url($u, PHP_URL_PATH)) && ! preg_match(self::CATEGORY_PATH, $u)) {
                $product = $u;
                break;
            }
        }
        if (! $product || ($html = $this->site->page($product)) === null) {
            return $this->skip('Tootelehte ei leitud.');
        }

        $has = (bool) preg_match('/"@type"\s*:\s*\[?\s*"Product"|schema\.org\/Product/i', $html);

        return [
            'status' => $has ? SeoAudit::RESULT_PASS : SeoAudit::RESULT_FAIL,
            'value'  => $product,
            'note'   => $has ? null : 'Tootelehel pole Product-andmeid — Google ei näita hinda ega saadavust otsingutulemuses.',
        ];
    }

    // ─── categories ─────────────────────────────────────────────────────────

    /** @return array<int, array{name:string, url:string}> */
    public function categories(string $origin): array
    {
        $links = $this->site->links($origin . '/', $this->site->home($origin));
        $anchors = [];
        foreach ($links as $l) {
            if ($l['text'] !== '' && mb_strlen($l['text']) <= 60) {
                $anchors[$l['url']] ??= $l['text'];
            }
        }

        $found = [];
        foreach (array_unique(array_merge($this->site->sitemapUrls($origin), array_keys($anchors))) as $u) {
            if (preg_match(self::CATEGORY_PATH, $u, $m)) {
                $name = $anchors[$u] ?? $this->humanize($m[2]);
                $found[mb_strtolower($name)] ??= ['name' => $name, 'url' => $u];
            }
        }

        // No category URLs: fall back to the main menu.
        if (! $found) {
            foreach ($links as $l) {
                $words = count(preg_split('/\s+/u', $l['text']) ?: []);
                if ($l['in_nav'] && $l['text'] !== '' && $words <= 5 && ! preg_match(self::NOT_CATEGORY, $l['text'])
                    && trim((string) parse_url($l['url'], PHP_URL_PATH), '/') !== '') {
                    $found[mb_strtolower($l['text'])] ??= ['name' => $l['text'], 'url' => $l['url']];
                }
            }
        }

        return array_slice(array_values($found), 0, max(1, Playbook::int('eshop.max_categories')));
    }

    /** @return string[] Google autocomplete phrases (Estonian, Estonia). */
    public function suggest(string $query): array
    {
        try {
            $r = Http::timeout(5)->get('https://suggestqueries.google.com/complete/search', [
                'client' => 'firefox', 'hl' => 'et', 'gl' => 'ee', 'ie' => 'utf-8', 'oe' => 'utf-8',
                'q' => mb_strtolower($query),
            ]);
            $data = json_decode($r->body(), true);
        } catch (\Throwable) {
            return [];
        }

        return array_slice(array_values(array_filter((array) ($data[1] ?? []), 'is_string')), 0, 8);
    }

    /**
     * @param  array<int, array{name:string, url:string, suggestions:string[]}> $cats
     * @return array<int, array{name:string, url:string, suggestions:string[], ok:?bool, better:?string, reason:?string}>
     */
    private function judge(array $cats, ?string $keyword): array
    {
        $answer = $this->ai->json(
            "Sa oled e-poodide SEO spetsialist. Hinda, kas iga kategooria nimi on sõnastatud nii, nagu inimesed Google'is otsivad.\n"
            . "Iga kategooria juures on Google'i otsingusoovitused (päris otsingud). Juhised:\n"
            . Playbook::get('ai.category_guidelines') . "\n\n"
            . 'Vasta JSON-ina: {"categories": [{"name": "<täpselt nagu sisendis>", "ok": true|false, '
            . '"better": "parem nimi või null", "reason": "lühike põhjendus eesti keeles"}]}',
            json_encode(['põhimärksõna' => $keyword, 'kategooriad' => array_map(
                fn ($c) => ['name' => $c['name'], 'google_soovitused' => $c['suggestions']],
                $cats
            )], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            1500,
        );

        $byName = [];
        foreach ((array) ($answer['categories'] ?? []) as $a) {
            if (is_array($a) && is_string($a['name'] ?? null)) {
                $byName[mb_strtolower(trim($a['name']))] = $a;
            }
        }

        return array_map(function ($c) use ($byName, $answer) {
            $a = $byName[mb_strtolower($c['name'])] ?? null;
            if ($a && is_bool($a['ok'] ?? null)) {
                return $c + [
                    'ok'     => $a['ok'],
                    'better' => ! $a['ok'] && is_string($a['better'] ?? null) ? mb_substr($a['better'], 0, 80) : null,
                    'reason' => is_string($a['reason'] ?? null) ? mb_substr($a['reason'], 0, 200) : null,
                ];
            }
            if ($answer !== null) {
                return $c + ['ok' => null, 'better' => null, 'reason' => 'AI ei hinnanud.'];
            }

            // No AI: a name Google autocompletes is phrased the way people search.
            $name = mb_strtolower($c['name']);
            $known = (bool) array_filter($c['suggestions'], fn ($s) => str_starts_with(mb_strtolower($s), $name));

            return $c + [
                'ok'     => $c['suggestions'] ? $known : null,
                'better' => ! $known ? ($c['suggestions'][0] ?? null) : null,
                'reason' => $c['suggestions'] ? null : 'Google ei pakkunud soovitusi.',
            ];
        }, $cats);
    }

    private function humanize(string $slug): string
    {
        $s = trim(preg_replace('/[-_]+/', ' ', urldecode($slug)));

        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    private function skip(string $note): array
    {
        return ['status' => SeoAudit::RESULT_SKIP, 'value' => null, 'note' => $note];
    }
}
