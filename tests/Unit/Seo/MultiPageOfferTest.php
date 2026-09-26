<?php

namespace Tests\Unit\Seo;

use App\Seo\Models\SeoAudit;
use App\Seo\Playbook;
use App\Seo\Services\SeoOfferService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MultiPageOfferTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('seo_audit_checks', function (Blueprint $t) {
            $t->id();
            $t->string('key');
            $t->string('fix_title')->nullable();
            $t->decimal('fix_price', 10, 2)->nullable();
            $t->decimal('fix_quantity', 10, 2)->default(1);
            $t->string('fix_unit')->nullable();
            $t->string('fix_group')->nullable();
            $t->integer('weight')->default(1);
            $t->integer('sort_order')->default(0);
        });
        DB::table('seo_audit_checks')->insert([
            ['key' => 'https',            'fix_title' => 'HTTPS',        'fix_price' => 60, 'fix_group' => 'Tehniline SEO korrastus', 'weight' => 5],
            ['key' => 'sitemap',          'fix_title' => 'Sitemap',      'fix_price' => 40, 'fix_group' => 'Tehniline SEO korrastus', 'weight' => 4],
            ['key' => 'title',            'fix_title' => 'Title',        'fix_price' => 80, 'fix_group' => 'Pealkirjad', 'weight' => 3],
            ['key' => 'meta_description', 'fix_title' => 'Meta',         'fix_price' => 80, 'fix_group' => 'Pealkirjad', 'weight' => 2],
            ['key' => 'word_count',       'fix_title' => 'Sisutekst',    'fix_price' => 150, 'fix_group' => null, 'weight' => 1],
        ]);

        (new \ReflectionProperty(Playbook::class, 'cache'))->setValue(null, [
            'offer.base_items'       => 'SEO lähteanalüüs | 1 | tk | 150',
            'offer.group_items'      => '1',
            'offer.site_wide_checks' => "https\nsitemap",
            'content.enabled'        => '0',
        ]);
    }

    private function audit(string $url, string $kw, array $fails): SeoAudit
    {
        $a = new SeoAudit(['url' => $url, 'keyword' => $kw, 'score' => 60]);
        $a->results = array_map(fn ($k) => ['key' => $k, 'status' => 'fail'], $fails);

        return $a;
    }

    public function test_site_wide_fixes_once_and_one_line_per_page(): void
    {
        $main = $this->audit('https://x.ee/elektritood/', 'elektritööd', ['https', 'sitemap', 'title', 'meta_description']);
        $page = $this->audit('https://x.ee/valgustus/', 'valgustus', ['sitemap', 'title', 'word_count']);

        $items = (new SeoOfferService())->multiPageItems($main, [$page]);
        $lines = array_map(fn ($i) => $i['description'] . ' = ' . $i['unit_price'], $items);

        $this->assertSame([
            'SEO lähteanalüüs = 150',
            'Tehniline SEO korrastus: HTTPS, Sitemap = 100',
            'Leht /elektritood/ („elektritööd“): Title, Meta = 160',
            'Leht /valgustus/ („valgustus“): Title, Sisutekst = 230',
        ], $lines);
    }

    public function test_description_lists_the_pages(): void
    {
        $main = $this->audit('https://x.ee/a/', 'a', []);
        $main->summary = 'Kokkuvõte.';

        $text = (new SeoOfferService())->description($main, [$this->audit('https://x.ee/b/', 'b', [])]);

        $this->assertStringContainsString("Kokkuvõte.\n\nAuditeeritud lehed:\n• https://x.ee/a/ („a“) — 60/100\n• https://x.ee/b/", $text);
    }
}
