<?php

namespace Tests\Unit\Seo;

use App\Outreach\Models\OutreachLead;
use App\Seo\Playbook;
use App\Seo\Services\AccessRequestService;
use App\Seo\Services\HostingDetector;
use App\Seo\Services\SiteCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccessRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        Playbook::flush();
        parent::tearDown();
    }

    private function seedPlaybook(array $values): void
    {
        // No test DB here: seed the Playbook's per-request cache directly.
        (new \ReflectionProperty(Playbook::class, 'cache'))->setValue(null, $values);
    }

    public function test_site_builder_is_detected_before_dns(): void
    {
        Http::fake(['lill.ee/*' => Http::response('<!doctype html><html><head><link href="https://static.voog.com/x.css"></head><body></body></html>')]);

        $r = (new HostingDetector(new SiteCrawler()))->detect('lill.ee');

        $this->assertSame('Voog', $r['provider']);
        $this->assertSame('platform', $r['kind']);
    }

    public function test_draft_uses_the_hosts_own_instructions(): void
    {
        $this->seedPlaybook(['clarify.gsc_email' => 'seo@webfight.ee', 'access.ssh_key' => 'ssh-ed25519 AAAAtest veiko']);
        $lead = new OutreachLead(['first_name' => 'Mari', 'company' => 'Katus OÜ', 'website' => 'katus.ee']);
        $service = new AccessRequestService(new HostingDetector(new SiteCrawler()));

        $zone = $service->draft($lead, ['provider' => 'Zone.ee', 'kind' => 'host']);
        $this->assertStringContainsString('Zone.ee halduspaneelis', $zone);
        $this->assertStringContainsString('ssh-ed25519 AAAAtest veiko', $zone);
        $this->assertStringContainsString('„Täielik“', $zone);
        $this->assertStringContainsString('seo@webfight.ee', $zone);

        $voog = $service->draft($lead, ['provider' => 'Voog', 'kind' => 'platform']);
        $this->assertStringContainsString('Voog konto halduriks', $voog);
        $this->assertStringNotContainsString('ssh-ed25519', $voog);

        $unknown = $service->draft($lead, null);
        $this->assertStringContainsString('WordPressi', $unknown);
        $this->assertStringNotContainsString('{{', $unknown);
    }

    public function test_trigger_stages(): void
    {
        $this->assertTrue(AccessRequestService::isTriggerStage('closed_won'));
        $this->assertTrue(AccessRequestService::isTriggerStage('töös'));
        $this->assertFalse(AccessRequestService::isTriggerStage('proposal'));
    }
}
