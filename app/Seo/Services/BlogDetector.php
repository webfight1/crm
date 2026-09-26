<?php

namespace App\Seo\Services;

/**
 * Does the site have a blog / articles section? Decides the content part of
 * the offer (SeoOfferService): no blog → set one up, blog → weekly articles.
 *
 * Signals: a posts sitemap (post-sitemap.xml), article-like URL paths
 * (/blogi/…, /uudised/…, /kasulikku/…, /2024/05/…) and a blog link in the main menu.
 */
class BlogDetector
{
    private const SECTION = '~/(blog|blogi|uudised|artiklid|nouanded|kasulik[\w-]*|news|articles|postitused)(/|$)~i';
    private const MENU    = '/^(blogi|blog|uudised|artiklid|nõuanded|kasulik(ku|\s+info|\s+teada)?|news|articles)$/iu';

    public function __construct(private readonly SiteCrawler $site) {}

    /** @return array{exists:bool, url:?string, posts:int, note:string} */
    public function detect(string $siteUrl): array
    {
        $origin = SiteCrawler::origin($siteUrl);
        if (! $origin) {
            return ['exists' => false, 'url' => null, 'posts' => 0, 'note' => 'Vigane aadress.'];
        }

        $urls = $this->site->sitemapUrls($origin);
        $postSitemap = (bool) preg_grep('/post-sitemap|posts?\.xml|blog/i', $this->site->sitemapFiles($origin));

        // Article pages: under a blog-like section, or dated WordPress-style paths.
        $posts = array_values(array_filter($urls, function ($u) {
            $path = (string) parse_url($u, PHP_URL_PATH);
            return (preg_match(self::SECTION, $path) && substr_count(trim($path, '/'), '/') >= 1)
                || preg_match('~/20\d{2}/\d{2}/[^/]+~', $path);
        }));

        $menuUrl = null;
        foreach ($this->site->links($origin . '/', $this->site->home($origin)) as $l) {
            if (preg_match(self::MENU, $l['text']) || preg_match(self::SECTION, (string) parse_url($l['url'], PHP_URL_PATH))) {
                $menuUrl = $l['url'];
                break;
            }
        }

        $exists = count($posts) >= 2 || ($postSitemap && $posts) || ($menuUrl && $posts);
        $url = $menuUrl;
        if (! $url && $posts && preg_match(self::SECTION, (string) parse_url($posts[0], PHP_URL_PATH), $m)) {
            $url = $origin . '/' . $m[1];
        }

        return [
            'exists' => $exists,
            'url'    => $exists ? $url : null,
            'posts'  => count($posts),
            'note'   => $exists
                ? 'Blogi on olemas' . ($posts ? ' (' . count($posts) . ' artiklit sitemapis)' : '') . '.'
                : ($menuUrl ? 'Menüüs on blogi link, aga artikleid ei leitud.' : 'Blogi / artiklite rubriiki ei leitud.'),
        ];
    }
}
