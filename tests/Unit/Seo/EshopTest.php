<?php

namespace Tests\Unit\Seo;

use App\Seo\Services\EshopAnalyzer;
use App\Seo\Services\SeoAi;
use App\Seo\Services\SiteCrawler;
use App\Seo\Services\SiteTypeDetector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EshopTest extends TestCase
{
    private function fakeShop(): void
    {
        Http::fake([
            'suggestqueries.google.com/*' => Http::response('["x",["meeste jalatsid","meeste jalatsid sale"]]'),
            'pood.ee/robots.txt'  => Http::response('', 404),
            'pood.ee/sitemap.xml' => Http::response('<?xml version="1.0"?><sitemapindex>'
                . '<sitemap><loc>https://pood.ee/product-sitemap.xml</loc></sitemap>'
                . '<sitemap><loc>https://pood.ee/product_cat-sitemap.xml</loc></sitemap></sitemapindex>'),
            'pood.ee/product_cat-sitemap.xml' => Http::response('<?xml version="1.0"?><urlset>'
                . '<url><loc>https://pood.ee/tootekategooria/meeste-jalatsid/</loc></url>'
                . '<url><loc>https://pood.ee/tootekategooria/jalanoud-meestele/</loc></url></urlset>'),
            'pood.ee/product-sitemap.xml' => Http::response('<?xml version="1.0"?><urlset>'
                . '<url><loc>https://pood.ee/toode/tossud-x/</loc></url></urlset>'),
            'pood.ee/toode/tossud-x*' => Http::response('<!doctype html><html><head><script type="application/ld+json">{"@type":"Product"}</script></head><body></body></html>'),
            'pood.ee/*' => Http::response('<!doctype html><html><head><title>Pood</title></head><body class="woocommerce">'
                . '<nav><a href="/tootekategooria/meeste-jalatsid/">Meeste jalatsid</a></nav>'
                . '<a href="/ostukorv/">Ostukorv</a><button class="add_to_cart_button">Lisa ostukorvi</button></body></html>'),
        ]);
    }

    public function test_detects_eshop_and_regular_site(): void
    {
        $this->fakeShop();
        $this->assertSame('eshop', (new SiteTypeDetector(new SiteCrawler()))->detect('pood.ee')['type']);

        Http::fake(['firma.ee/*' => Http::response('<!doctype html><html><head><title>Firma</title></head><body><a href="/teenused">Teenused</a></body></html>')]);
        $this->assertSame('service', (new SiteTypeDetector(new SiteCrawler()))->detect('firma.ee')['type']);
    }

    public function test_category_names_are_compared_with_google_suggestions_without_ai(): void
    {
        $this->fakeShop();
        config(['services.openai.key' => null]);

        [$result, $cats] = (new EshopAnalyzer(new SiteCrawler(), new SeoAi()))->categoryCheck('https://pood.ee', 'meeste jalatsid');

        $byName = array_column($cats, null, 'name');
        $this->assertTrue($byName['Meeste jalatsid']['ok']);          // name from menu link text
        $this->assertFalse($byName['Jalanoud meestele']['ok']);      // name from slug
        $this->assertSame('meeste jalatsid', $byName['Jalanoud meestele']['better']);
        $this->assertSame('fail', $result['status']);                // 50% < 70%
    }

    public function test_product_schema(): void
    {
        $this->fakeShop();
        $r = (new EshopAnalyzer(new SiteCrawler(), new SeoAi()))->productSchemaCheck('https://pood.ee');
        $this->assertSame('pass', $r['status']);
    }
}
