<?php

namespace Tests\Unit\Seo;

use App\Seo\Services\LandingPageFinder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LandingPageFinderTest extends TestCase
{
    private function page(string $title, string $h1, string $body = ''): string
    {
        return "<!doctype html><html><head><title>{$title}</title></head><body><h1>{$h1}</h1>{$body}</body></html>";
    }

    public function test_finds_service_page_from_sitemap(): void
    {
        Http::fake([
            'katus.ee/robots.txt'  => Http::response("User-agent: *\nSitemap: https://katus.ee/sitemap.xml"),
            'katus.ee/sitemap.xml' => Http::response('<?xml version="1.0"?><urlset>'
                . '<url><loc>https://katus.ee/</loc></url>'
                . '<url><loc>https://katus.ee/meist/</loc></url>'
                . '<url><loc>https://katus.ee/teenused/katusetood-tartus/</loc></url>'
                . '<url><loc>https://katus.ee/teenused/vihmaveesusteemid/</loc></url>'
                . '</urlset>'),
            'katus.ee/teenused/katusetood-tartus*' => Http::response($this->page('Katusetööd Tartus | Katus OÜ', 'Katusetööd Tartus')),
            'katus.ee/*' => Http::response($this->page('Katus OÜ', 'Tere tulemast')),
        ]);

        $r = (new LandingPageFinder())->find('katus.ee', 'katusetööd tartu');

        $this->assertSame('found', $r['source']);
        $this->assertSame('https://katus.ee/teenused/katusetood-tartus', $r['url']);
    }

    public function test_finds_page_from_menu_anchor_when_no_sitemap(): void
    {
        Http::fake([
            'volt.ee/robots.txt' => Http::response('', 404),
            'volt.ee/sitemap*'   => Http::response('', 404),
            'volt.ee/wp-sitemap.xml' => Http::response('', 404),
            'volt.ee/p/12*'      => Http::response($this->page('Elektritööd | Volt', 'Elektritööd Tartus')),
            'volt.ee/*'          => Http::response($this->page('Volt OÜ', 'Volt', '<nav><a href="/p/12">Elektritööd</a><a href="/kontakt">Kontakt</a></nav>')),
        ]);

        $r = (new LandingPageFinder())->find('https://volt.ee', 'elektritööd tartus');

        $this->assertSame('found', $r['source']);
        $this->assertSame('https://volt.ee/p/12', $r['url']);
    }

    public function test_homepage_or_none(): void
    {
        Http::fake([
            'uks.ee/robots.txt' => Http::response('', 404),
            'uks.ee/sitemap*'   => Http::response('', 404),
            'uks.ee/wp-sitemap.xml' => Http::response('', 404),
            'uks.ee/*'          => Http::response($this->page('Uksed Tallinnas – Uks OÜ', 'Uksed')),
        ]);

        $finder = new LandingPageFinder();
        $this->assertSame('home', $finder->find('uks.ee', 'uksed tallinn')['source']);
        $this->assertSame('none', $finder->find('uks.ee', 'aknad tartu')['source']);
    }
}
