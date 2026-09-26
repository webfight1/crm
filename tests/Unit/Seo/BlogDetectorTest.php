<?php

namespace Tests\Unit\Seo;

use App\Seo\Services\BlogDetector;
use App\Seo\Services\SiteCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlogDetectorTest extends TestCase
{
    public function test_blog_found_from_sitemap_and_menu(): void
    {
        Http::fake([
            'firma.ee/robots.txt'  => Http::response('', 404),
            'firma.ee/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset>'
                . '<url><loc>https://firma.ee/teenused/</loc></url>'
                . '<url><loc>https://firma.ee/blogi/kuidas-valida-katust/</loc></url>'
                . '<url><loc>https://firma.ee/blogi/katuse-hooldus-talvel/</loc></url></urlset>'),
            'firma.ee/*' => Http::response('<!doctype html><html><head></head><body><nav><a href="/blogi/">Blogi</a></nav></body></html>'),
        ]);

        $r = (new BlogDetector(new SiteCrawler()))->detect('firma.ee');

        $this->assertTrue($r['exists']);
        $this->assertSame(2, $r['posts']);
        $this->assertSame('https://firma.ee/blogi', $r['url']);
    }

    public function test_no_blog(): void
    {
        Http::fake([
            'uks.ee/robots.txt'  => Http::response('', 404),
            'uks.ee/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset><url><loc>https://uks.ee/kontakt/</loc></url></urlset>'),
            'uks.ee/*' => Http::response('<!doctype html><html><head></head><body><nav><a href="/kontakt">Kontakt</a></nav></body></html>'),
        ]);

        $this->assertFalse((new BlogDetector(new SiteCrawler()))->detect('uks.ee')['exists']);
    }

    public function test_kasulikku_section_counts_as_blog(): void
    {
        Http::fake([
            'aken.ee/robots.txt'  => Http::response('', 404),
            'aken.ee/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset>'
                . '<url><loc>https://aken.ee/kasulikku/kuidas-valida-aknaid/</loc></url>'
                . '<url><loc>https://aken.ee/kasulikku/akende-hooldus/</loc></url></urlset>'),
            'aken.ee/*' => Http::response('<!doctype html><html><head></head><body><nav><a href="/kasulikku/">Kasulikku</a></nav></body></html>'),
        ]);

        $r = (new BlogDetector(new SiteCrawler()))->detect('aken.ee');

        $this->assertTrue($r['exists']);
        $this->assertSame('https://aken.ee/kasulikku', $r['url']);
    }
}
