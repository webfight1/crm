<?php

namespace Tests\Unit\Seo;

use App\Outreach\Models\OutreachLead;
use App\Seo\Models\SeoAudit;
use App\Seo\Playbook;
use App\Seo\Services\SeoMonitorClient;
use App\Seo\Services\SeoMonitorSyncService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoMonitorPageSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.seo_monitor' => ['api_url' => 'https://seo.test/api/v1', 'token' => 't', 'app_url' => 'https://seo.test']]);
        (new \ReflectionProperty(Playbook::class, 'cache'))->setValue(null, ['monitor.enabled' => '1']);
    }

    private function page(array $attrs, ?int $projectId): SeoAudit
    {
        $page = new SeoAudit(['url' => 'https://x.ee/valgustus/', 'keyword' => 'valgustus', 'status' => SeoAudit::STATUS_DONE] + $attrs);
        $page->setRelation('lead', new OutreachLead(['seo_monitor_project_id' => $projectId]));

        return $page;
    }

    public function test_extra_page_goes_to_positions_and_pages(): void
    {
        Http::fake([
            'seo.test/api/v1/projects/5' => Http::response(['data' => ['id' => 5, 'url' => 'https://x.ee/']]),
            'seo.test/*' => Http::response(['data' => ['id' => 1]], 201),
        ]);

        (new SeoMonitorSyncService(new SeoMonitorClient()))->syncPage($this->page(['main_audit_id' => 10], 5));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'projects/5/keywords')
            && $r['keyword'] === 'valgustus' && $r['target_url'] === 'https://x.ee/valgustus/');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'projects/5/pages') && $r['url'] === 'https://x.ee/valgustus/');
    }

    public function test_page_is_put_in_the_projects_form(): void
    {
        $this->assertSame('https://elektritood.eu/kait/', SeoMonitorClient::onSite('http://www.elektritood.eu/kait/?a=1#x', 'https://elektritood.eu/'));
        $this->assertSame('https://www.x.ee/', SeoMonitorClient::onSite('http://x.ee', 'https://www.x.ee/'));
        $this->assertSame('https://muu.ee/a', SeoMonitorClient::onSite('https://muu.ee/a?b', 'https://x.ee/'));
    }

    public function test_rejected_target_still_adds_the_keyword(): void
    {
        Http::fake(['seo.test/api/v1/projects/5/keywords' => Http::sequence()
            ->push(['errors' => ['target_url' => ['vale']]], 422)
            ->push(['data' => ['id' => 1]], 201)]);

        $this->assertTrue((new SeoMonitorClient())->addKeyword(5, 'valgustus', 'https://muu.ee/'));
        Http::assertSent(fn ($r) => $r['keyword'] === 'valgustus' && ! isset($r['target_url']));
    }

    public function test_nothing_without_a_project_or_for_the_main_audit(): void
    {
        Http::fake();
        $sync = new SeoMonitorSyncService(new SeoMonitorClient());

        $sync->syncPage($this->page(['main_audit_id' => 10], null));
        $sync->syncPage($this->page([], 5));

        Http::assertNothingSent();
    }

    public function test_typed_keywords_go_to_positions(): void
    {
        Http::fake([
            'seo.test/api/v1/projects/5' => Http::response(['data' => ['id' => 5, 'url' => 'https://x.ee/']]),
            'seo.test/*' => Http::response(['data' => ['id' => 1]], 201),
        ]);

        $added = (new SeoMonitorSyncService(new SeoMonitorClient()))
            ->addKeywords(5, "elektrik tallinn\nhttp://www.x.ee/kilbid | kilbid\n\n");

        $this->assertSame(2, $added);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'projects/5/keywords')
            && $r['keyword'] === 'elektrik tallinn' && ! isset($r['target_url']));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'projects/5/keywords')
            && $r['keyword'] === 'kilbid' && $r['target_url'] === 'https://x.ee/kilbid');
    }

    public function test_a_url_line_is_the_page_for_the_keywords_below_it(): void
    {
        Http::fake([
            'seo.test/api/v1/projects/5' => Http::response(['data' => ['id' => 5, 'url' => 'https://x.ee/']]),
            'seo.test/*' => Http::response(['data' => ['id' => 1]], 201),
        ]);

        $added = (new SeoMonitorSyncService(new SeoMonitorClient()))
            ->addKeywords(5, "üldine\nhttps://x.ee/projektid/\ntrükised\nkujundus | https://x.ee/kujundus\nkleebised");

        $this->assertSame(4, $added);
        $target = fn ($kw) => Http::recorded(fn ($r) => str_ends_with($r->url(), 'keywords') && $r['keyword'] === $kw)->first()[0]['target_url'] ?? null;
        $this->assertNull($target('üldine'));
        $this->assertSame('https://x.ee/projektid/', $target('trükised'));
        $this->assertSame('https://x.ee/kujundus', $target('kujundus'));
        $this->assertSame('https://x.ee/projektid/', $target('kleebised'));
    }

    public function test_an_existing_project_of_the_same_domain_is_reused(): void
    {
        Http::fake([
            'seo.test/api/v1/projects' => Http::response(['data' => [['id' => 6, 'domain' => 'www.kind.ee']]]),
            'seo.test/*' => Http::response(['data' => ['id' => 1]], 201),
        ]);
        $sync = new SeoMonitorSyncService(new SeoMonitorClient());
        $method = new \ReflectionMethod($sync, 'projectForDomain');

        $this->assertSame(6, $method->invoke($sync, 'kind.ee'));
        $this->assertNull($method->invoke($sync, 'muu.ee'));
    }
}
