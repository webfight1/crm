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

    /** @return array{Connected: bool, LoggedIn: bool, jid?: string}|null */
    public function status(): ?array
    {
        return $this->call('get', '/session/status');
    }

    /** Point WuzAPI's webhook at the CRM and open the WhatsApp connection. */
    public function connect(): ?array
    {
        $webhook = route('chats.whatsapp.webhook', config('services.wuzapi.webhook_secret'));
        if ($this->call('post', '/webhook', ['webhookURL' => $webhook, 'events' => ['Message']]) === null) {
            return null;
        }

        return $this->call('post', '/session/connect', ['Subscribe' => ['Message'], 'Immediate' => true]);
    }

    /** data:image/png;base64,… or null when already logged in / not ready yet */
    public function qr(): ?string
    {
        return data_get($this->call('get', '/session/qr'), 'QRCode') ?: null;
    }

    public function logout(): ?array
    {
        return $this->call('post', '/session/logout');
    }

    private function call(string $method, string $path, array $body = []): ?array
    {
        try {
            $response = Http::withHeaders(['Token' => config('services.wuzapi.token')])
                ->timeout(15)
                ->{$method}(rtrim(config('services.wuzapi.url'), '/') . $path, $body ?: null);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? (array) $response->json('data', []) : null;
    }
}
