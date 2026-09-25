<?php

namespace App\Seo\Services;

/**
 * Finds the page on a site that targets a keyword ("katusetööd tartu" →
 * firma.ee/teenused/katusetood; on an e-shop usually a category page).
 *
 *   1. Candidates = sitemap URLs + the homepage's internal links with their
 *      anchor text (SiteCrawler).
 *   2. Each candidate is scored by keyword coverage of its URL slug + anchor.
 *   3. The best few are fetched and scored again on <title> and <h1>.
 *
 * Returns the best page, or the homepage if it clearly targets the keyword
 * itself (small one-service sites), or nothing.
 */
class LandingPageFinder
{
    private const FETCH_TOP = 5;

    public function __construct(private readonly SiteCrawler $site) {}

    /**
     * @return array{url:?string, source:string, note:string}
     *   source: found (a subpage) | home (homepage targets it) | none
     */
    public function find(string $siteUrl, string $keyword): array
    {
        $origin = SiteCrawler::origin($siteUrl);
        if ($origin === null || trim($keyword) === '') {
            return ['url' => null, 'source' => 'none', 'note' => 'Aadress või märksõna puudub.'];
        }

        $homeHtml = $this->site->home($origin);
        $candidates = $this->candidates($origin, $homeHtml);

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
            $html = $this->site->page($url);
            if ($html === null) {
                continue;
            }
            [$title, $h1] = self::titleAndH1($html);
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

        if ($homeHtml !== null) {
            [$title, $h1] = self::titleAndH1($homeHtml);
            if (PageAnalyzer::containsKeyword($title, $keyword) || PageAnalyzer::containsKeyword($h1, $keyword)) {
                return ['url' => $origin . '/', 'source' => 'home', 'note' => 'Eraldi alamlehte pole, aga avaleht on märksõnale suunatud.'];
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

    /** @return array{0:?string, 1:?string} */
    public static function titleAndH1(string $html): array
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

    /** @return array<string, string> url => anchor text */
    private function candidates(string $origin, ?string $homeHtml): array
    {
        $out = array_fill_keys($this->site->sitemapUrls($origin), '');
        foreach ($this->site->links($origin . '/', $homeHtml) as $l) {
            $out[$l['url']] = trim(($out[$l['url']] ?? '') . ' ' . $l['text']);
        }

        return $out;
    }
}
