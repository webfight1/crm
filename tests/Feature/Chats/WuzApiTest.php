<?php

namespace Tests\Feature\Chats;

use App\Chats\Services\WuzApi;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WuzApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.seo_pipeline' => false, 'services.wuzapi.url' => 'http://wuz',
                'services.wuzapi.token' => 't', 'services.wuzapi.webhook_secret' => 's']);
    }

    public function test_status_reads_lowercase_keys_and_qr_from_real_response_shape(): void
    {
        Http::fake(['wuz/session/status' => Http::response(['code' => 200, 'success' => true, 'data' => [
            'connected' => true, 'loggedIn' => false, 'qrcode' => 'data:image/png;base64,AAA', 'jid' => '',
        ]])]);

        $this->assertSame(['connected' => true, 'loggedIn' => false, 'qr' => 'data:image/png;base64,AAA'], app(WuzApi::class)->status());
    }

    public function test_connect_treats_already_connected_as_success(): void
    {
        Http::fake([
            'wuz/webhook'         => Http::response(['code' => 200, 'success' => true, 'data' => ['webhook' => 'x']]),
            'wuz/session/connect' => Http::response(['code' => 409, 'success' => false, 'error' => 'already connected'], 409),
        ]);

        $this->assertNotNull(app(WuzApi::class)->connect());
        Http::assertSent(fn ($r) => $r->url() === 'http://wuz/webhook'
            && str_ends_with($r['webhookURL'], '/api/chats/whatsapp/s'));
    }
}
