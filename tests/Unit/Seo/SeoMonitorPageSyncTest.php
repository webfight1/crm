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
        Http::fake(['seo.test/*' => Http::response(['data' => ['id' => 1]], 201)]);

        (new SeoMonitorSyncService(new SeoMonitorClient()))->syncPage($this->page(['main_audit_id' => 10], 5));

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'projects/5/keywords')
            && $r['keyword'] === 'valgustus' && $r['target_url'] === 'https://x.ee/valgustus/');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'projects/5/pages') && $r['url'] === 'https://x.ee/valgustus/');
    }

    public function test_nothing_without_a_project_or_for_the_main_audit(): void
    {
        Http::fake();
        $sync = new SeoMonitorSyncService(new SeoMonitorClient());

        $sync->syncPage($this->page(['main_audit_id' => 10], null));
        $sync->syncPage($this->page([], 5));

        Http::assertNothingSent();
    }
}
