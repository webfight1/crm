<?php

namespace App\Seo\Services;

use App\Seo\Models\SeoAudit;
use Symfony\Component\DomCrawler\Crawler;

/**
 * PageAnalyzer
 *
 * Pure, offline part of the SEO audit: given a page's HTML it extracts the
 * on-page facts and evaluates every builtin check that needs nothing but the
 * HTML. Network checks (PageSpeed, sitemap, robots.txt) live in
 * SeoAuditService.
 *
 * Each check returns ['status' => pass|fail|skip, 'value' => observed, 'note' => ?string].
 * Adding a new builtin check = add a method here + a row in seo_audit_checks
 * with the same key.
 */
class PageAnalyzer
{
    /** Keys this class can evaluate. */
    public const KEYS = [
        'https', 'noindex', 'title', 'keyword_in_title', 'meta_description',
        'h1', 'keyword_in_h1', 'word_count', 'viewport', 'image_alt',
        'canonical', 'schema',
    ];

    /** @var array<string, mixed> */
    private array $facts = [];

    public function __construct(
        private readonly string $html,
        private readonly string $url,
        private readonly ?string $keyword = null,
    ) {
        $this->facts = $this->extract();
    }

    /** @return array<string, mixed> Extracted page facts, handy as AI context. */
    public function facts(): array
    {
        return $this->facts;
    }

    /** @return array{status:string, value:mixed, note:?string}|null null = unknown key */
    public function check(string $key): ?array
    {
        $f = $this->facts;

        return match ($key) {
            'https' => $this->result(
                str_starts_with(strtolower($this->url), 'https://'),
                parse_url($this->url, PHP_URL_SCHEME)
            ),
            'noindex' => $this->result(
                ! $f['noindex'],
                $f['robots_meta'] ?: 'puudub',
                $f['noindex'] ? 'Meta robots sisaldab "noindex".' : null
            ),
            'title' => $this->lengthResult($f['title'], 10, 65, 'Pealkiri'),
            'meta_description' => $this->lengthResult($f['meta_description'], 50, 160, 'Kirjeldus'),
            'h1' => $this->result(
                $f['h1_count'] === 1,
                $f['h1_count'] . ' tk' . ($f['h1'] ? ': ' . $f['h1'] : ''),
                $f['h1_count'] === 0 ? 'H1 pealkiri puudub.' : ($f['h1_count'] > 1 ? 'Mitu H1 pealkirja.' : null)
            ),
            'keyword_in_title' => $this->keywordResult($f['title']),
            'keyword_in_h1'    => $this->keywordResult($f['h1']),
            'word_count' => $this->result($f['word_count'] >= 300, $f['word_count'] . ' sõna'),
            'viewport'   => $this->result($f['has_viewport'], $f['has_viewport'] ? 'olemas' : 'puudub'),
            'image_alt'  => $f['image_count'] === 0
                ? $this->skip('Pilte ei leitud.')
                : $this->result(
                    $f['image_alt_ratio'] >= 0.8,
                    round($f['image_alt_ratio'] * 100) . '% piltidest (' . $f['image_count'] . ' tk)'
                ),
            'canonical' => $this->result($f['canonical'] !== null, $f['canonical'] ?: 'puudub'),
            'schema'    => $this->result($f['has_schema'], $f['has_schema'] ? 'olemas' : 'puudub'),
            default     => null,
        };
    }

    /**
     * Does every significant word of the keyword appear in $text?
     * Estonian inflects heavily ("Tartu" / "Tartus" / "Tartusse"), so words are
     * compared by stem: the first max(4, len-2) characters.
     */
    public static function containsKeyword(?string $text, string $keyword): bool
    {
        $text = mb_strtolower((string) $text);
        if ($text === '') {
            return false;
        }

        $words = array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($keyword)) ?: [],
            fn ($w) => mb_strlen($w) >= 3
        );
        if (! $words) {
            return false;
        }

        foreach ($words as $w) {
            $stem = mb_substr($w, 0, max(4, mb_strlen($w) - 2));
            if (! str_contains($text, $stem)) {
                return false;
            }
        }

        return true;
    }

    // ─── internals ───────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function extract(): array
    {
        $c = new Crawler();
        $c->addHtmlContent($this->html, 'UTF-8');

        $text = function (string $sel) use ($c): ?string {
            try {
                $n = $c->filter($sel)->first();
                $t = $n->count() ? trim(preg_replace('/\s+/u', ' ', $n->text('')) ?? '') : '';
                return $t !== '' ? $t : null;
            } catch (\Throwable) {
                return null;
            }
        };
        $attr = function (string $sel, string $a) use ($c): ?string {
            try {
                $n = $c->filter($sel)->first();
                $v = $n->count() ? trim((string) $n->attr($a)) : '';
                return $v !== '' ? $v : null;
            } catch (\Throwable) {
                return null;
            }
        };
        $count = function (string $sel) use ($c): int {
            try {
                return $c->filter($sel)->count();
            } catch (\Throwable) {
                return 0;
            }
        };

        $robots = $attr('meta[name="robots"]', 'content');

        $images = 0;
        $withAlt = 0;
        try {
            $c->filter('img')->each(function (Crawler $img) use (&$images, &$withAlt) {
                $images++;
                if (trim((string) $img->attr('alt')) !== '') {
                    $withAlt++;
                }
            });
        } catch (\Throwable) {
        }

        // Visible text: drop scripts/styles before counting words.
        $body = new Crawler();
        $body->addHtmlContent($this->html, 'UTF-8');
        try {
            $body->filter('script, style, noscript, template')->each(function (Crawler $n) {
                foreach ($n as $node) {
                    $node->parentNode?->removeChild($node);
                }
            });
            $visible = $body->filter('body')->count() ? $body->filter('body')->text('') : '';
        } catch (\Throwable) {
            $visible = '';
        }
        $visible = trim(preg_replace('/\s+/u', ' ', $visible) ?? '');

        return [
            'title'            => $text('title'),
            'meta_description' => $attr('meta[name="description"]', 'content'),
            'h1'               => $text('h1'),
            'h1_count'         => $count('h1'),
            'h2'               => array_slice($this->texts($c, 'h2'), 0, 10),
            'robots_meta'      => $robots,
            'noindex'          => $robots !== null && str_contains(strtolower($robots), 'noindex'),
            'canonical'        => $attr('link[rel="canonical"]', 'href'),
            'has_viewport'     => $count('meta[name="viewport"]') > 0,
            'has_schema'       => $count('script[type="application/ld+json"]') > 0 || $count('[itemscope]') > 0,
            'image_count'      => $images,
            'image_alt_ratio'  => $images ? $withAlt / $images : 1.0,
            'word_count'       => $visible === '' ? 0 : count(preg_split('/\s+/u', $visible) ?: []),
            'has_tel'          => $count('a[href^="tel:"]') > 0,
            'has_mailto'       => $count('a[href^="mailto:"]') > 0,
            'has_form'         => $count('form') > 0,
            'text_excerpt'     => mb_substr($visible, 0, 2500),
        ];
    }

    /** @return string[] */
    private function texts(Crawler $c, string $sel): array
    {
        try {
            return array_values(array_filter(array_map(
                fn ($t) => trim(preg_replace('/\s+/u', ' ', $t) ?? ''),
                $c->filter($sel)->each(fn (Crawler $n) => $n->text(''))
            )));
        } catch (\Throwable) {
            return [];
        }
    }

    private function keywordResult(?string $text): array
    {
        if (! $this->keyword) {
            return $this->skip('Märksõna pole teada.');
        }

        return $this->result(
            self::containsKeyword($text, $this->keyword),
            $text ?: 'puudub',
            null
        );
    }

    private function lengthResult(?string $value, int $min, int $max, string $what): array
    {
        if ($value === null) {
            return $this->result(false, 'puudub', "{$what} puudub.");
        }
        $len = mb_strlen($value);
        $note = $len < $min ? "{$what} on liiga lühike ({$len} märki)."
            : ($len > $max ? "{$what} on liiga pikk ({$len} märki), Google lõikab selle ära." : null);

        return $this->result($note === null, $value, $note);
    }

    private function result(bool $pass, mixed $value, ?string $note = null): array
    {
        return [
            'status' => $pass ? SeoAudit::RESULT_PASS : SeoAudit::RESULT_FAIL,
            'value'  => $value,
            'note'   => $note,
        ];
    }

    private function skip(string $note): array
    {
        return ['status' => SeoAudit::RESULT_SKIP, 'value' => null, 'note' => $note];
    }
}
