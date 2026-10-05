<?php

namespace App\Chats\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client for the local WuzAPI container — only the session calls the
 * CRM needs: status, connect (registers our webhook) and the login QR.
 * Every call returns null on failure so the connect page can show
 * "WuzAPI ei vasta" instead of an exception.
 */
class WuzApi
{
    public function enabled(): bool
    {
        return filled(config('services.wuzapi.token')) && filled(config('services.wuzapi.webhook_secret'));
    }

    /**
     * WuzAPI answers with lower-case keys (connected, loggedIn, qrcode) —
     * normalised here so the controller doesn't care.
     *
     * @return array{connected: bool, loggedIn: bool, qr: ?string}|null
     */
    public function status(): ?array
    {
        $data = $this->call('get', '/session/status');
        if ($data === null) {
            return null;
        }

        return [
            'connected' => (bool) ($data['connected'] ?? $data['Connected'] ?? false),
            'loggedIn'  => (bool) ($data['loggedIn'] ?? $data['LoggedIn'] ?? false),
            'qr'        => ($data['qrcode'] ?? null) ?: null,
        ];
    }

    /** Point WuzAPI's webhook at the CRM and open the WhatsApp connection. */
    public function connect(): ?array
    {
        $webhook = route('chats.whatsapp.webhook', config('services.wuzapi.webhook_secret'));
        if ($this->call('post', '/webhook', ['webhookURL' => $webhook, 'events' => ['Message']]) === null) {
            return null;
        }

        // 409 "already connected" = the session is up, just not scanned yet.
        return $this->call('post', '/session/connect', ['Subscribe' => ['Message'], 'Immediate' => true], [409]);
    }

    public function logout(): ?array
    {
        return $this->call('post', '/session/logout');
    }

    private function call(string $method, string $path, array $body = [], array $okStatuses = []): ?array
    {
        try {
            $response = Http::withHeaders(['Token' => config('services.wuzapi.token')])
                ->timeout(15)
                ->{$method}(rtrim(config('services.wuzapi.url'), '/') . $path, $body ?: null);
        } catch (Throwable) {
            return null;
        }

        if (in_array($response->status(), $okStatuses, true)) {
            return [];
        }

        return $response->successful() ? (array) $response->json('data', []) : null;
    }
}
