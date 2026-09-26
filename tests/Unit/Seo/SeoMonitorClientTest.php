<?php

namespace Tests\Unit\Seo;

use App\Seo\Services\SeoMonitorClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SeoMonitorClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.seo_monitor' => ['api_url' => 'https://seo.test/api/v1', 'token' => 't', 'app_url' => 'https://seo.test']]);
    }

    public function test_existing_user_gets_the_project_added(): void
    {
        Http::fake([
            'seo.test/api/v1/users' => Http::response(['data' => [
                ['id' => 7, 'email' => 'Mari@Katus.ee', 'projects' => [['id' => 3, 'name' => 'Vana']]],
            ]]),
            'seo.test/api/v1/users/7' => Http::response(['data' => ['id' => 7]]),
        ]);

        $this->assertSame(7, (new SeoMonitorClient())->grantClient(12, 'mari@katus.ee', 'Mari'));

        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && $r['project_ids'] === [3, 12]);
    }

    public function test_new_user_is_a_client_with_only_this_project(): void
    {
        Http::fake([
            'seo.test/api/v1/users' => Http::sequence()
                ->push(['data' => []])
                ->push(['data' => ['id' => 9]], 201),
        ]);

        $this->assertSame(9, (new SeoMonitorClient())->grantClient(12, 'uus@firma.ee', 'Uus'));

        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['role'] === 'client'
            && $r['project_ids'] === [12] && strlen($r['password']) >= 12);
    }

    public function test_invite_link_is_optional_until_seo_monitor_has_invites(): void
    {
        Http::fake(['seo.test/api/v1/users/9/invite' => Http::response(['message' => 'Not Found'], 404)]);
        $this->assertNull((new SeoMonitorClient())->inviteUrl(9));
    }

    public function test_invite_link_is_returned(): void
    {
        Http::fake(['seo.test/api/v1/users/9/invite' => Http::response(['invite_url' => 'https://seo.test/invite/x'])]);
        $this->assertSame('https://seo.test/invite/x', (new SeoMonitorClient())->inviteUrl(9));
    }

    public function test_project_url(): void
    {
        $this->assertSame('https://seo.test/projects/5', (new SeoMonitorClient())->projectUrl(5));
    }

    public function test_deleted_project_is_detected(): void
    {
        Http::fake([
            'seo.test/api/v1/projects/3' => Http::response(['message' => 'Not Found'], 404),
            'seo.test/api/v1/projects/4' => Http::response(['data' => ['id' => 4]]),
        ]);

        $this->assertFalse((new SeoMonitorClient())->projectExists(3));
        $this->assertTrue((new SeoMonitorClient())->projectExists(4));
    }
}
