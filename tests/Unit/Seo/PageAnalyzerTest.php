<?php

namespace Tests\Unit\Seo;

use App\Seo\Services\PageAnalyzer;
use PHPUnit\Framework\TestCase;

class PageAnalyzerTest extends TestCase
{
    private function page(string $head, string $body): string
    {
        return "<!doctype html><html><head>{$head}</head><body>{$body}</body></html>";
    }

    public function test_good_page_passes_core_checks(): void
    {
        $html = $this->page(
            '<title>Elektritööd Tartus – kiire ja korralik | Volt OÜ</title>'
            . '<meta name="description" content="Teeme elektritöid Tartus ja Tartumaal: paigaldus, remont ja hooldus. Helista ja küsi pakkumist juba täna.">'
            . '<meta name="viewport" content="width=device-width">'
            . '<link rel="canonical" href="https://volt.ee/">'
            . '<script type="application/ld+json">{}</script>',
            '<h1>Elektritööd Tartus</h1><img src="a.jpg" alt="Elektrik"><p>' . str_repeat('sõna ', 320) . '</p>'
        );
        $a = new PageAnalyzer($html, 'https://volt.ee/', 'elektritööd tartus');

        foreach (['https', 'noindex', 'title', 'meta_description', 'h1', 'keyword_in_title',
                  'keyword_in_h1', 'word_count', 'viewport', 'image_alt', 'canonical', 'schema'] as $key) {
            $this->assertSame('pass', $a->check($key)['status'], "check {$key}");
        }
    }

    public function test_weak_page_fails(): void
    {
        $html = $this->page('<meta name="robots" content="noindex,follow"><title>Avaleht</title>', '<p>Tere</p><img src="x.png">');
        $a = new PageAnalyzer($html, 'http://volt.ee/', 'elektritööd tartus');

        $this->assertSame('fail', $a->check('https')['status']);
        $this->assertSame('fail', $a->check('noindex')['status']);
        $this->assertSame('fail', $a->check('title')['status']);            // too short
        $this->assertSame('fail', $a->check('meta_description')['status']); // missing
        $this->assertSame('fail', $a->check('h1')['status']);
        $this->assertSame('fail', $a->check('keyword_in_title')['status']);
        $this->assertSame('fail', $a->check('image_alt')['status']);
        $this->assertSame('fail', $a->check('word_count')['status']);
    }

    public function test_keyword_checks_skip_without_keyword_and_unknown_key_is_null(): void
    {
        $a = new PageAnalyzer($this->page('<title>x</title>', ''), 'https://x.ee');

        $this->assertSame('skip', $a->check('keyword_in_title')['status']);
        $this->assertNull($a->check('pagespeed_mobile'));
    }

    public function test_keyword_match_tolerates_estonian_inflection(): void
    {
        $this->assertTrue(PageAnalyzer::containsKeyword('Elektritööd Tartu linnas', 'elektritööd tartus'));
        $this->assertTrue(PageAnalyzer::containsKeyword('Katuse remont – Katuseremondi OÜ', 'katuse remont'));
        $this->assertFalse(PageAnalyzer::containsKeyword('Santehnika Tartus', 'elektritööd tartus'));
        $this->assertFalse(PageAnalyzer::containsKeyword(null, 'elektritööd'));
    }

    public function test_keyword_coverage_folds_diacritics_for_url_slugs(): void
    {
        $this->assertSame(1.0, PageAnalyzer::keywordCoverage('teenused katusetood tartus', 'katusetööd tartu'));
        $this->assertSame(0.5, PageAnalyzer::keywordCoverage('teenused katusetood', 'katusetööd tartu'));
        $this->assertSame(0.0, PageAnalyzer::keywordCoverage('', 'katusetööd'));
    }
}
