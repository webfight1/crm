<?php

namespace App\Chats\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Client for the mautrix-meta provisioning API (login with messenger.com
 * cookies, status, logout). Cookies are forwarded once and never stored in
 * the CRM. Calls return null when the bridge is unreachable.
 */
class MessengerBridge
{
    private const SELF_KEY = 'chats.messenger.self_id';

    public function enabled(): bool
    {
        return filled(config('services.messenger.provision_secret')) && filled(config('services.messenger.hs_token'));
    }

    /** @return array{reachable: bool, loggedIn: bool, name: ?string, state: ?string} */
    public function status(): array
    {
        $who = $this->call('get', '/v3/whoami');
        $login = data_get($who, 'logins.0');
        if ($login) {
            Cache::forever(self::SELF_KEY, (string) $login['id']);
        }

        return [
            'reachable' => $who !== null,
            'loggedIn'  => (bool) $login,
            'name'      => data_get($login, 'name') ?: data_get($login, 'profile.name'),
            'state'     => data_get($login, 'state.state_event'),
        ];
    }

    /** Our own Facebook id — messages from its ghost are the ones we sent from the phone. */
    public function selfId(): ?string
    {
        if (! Cache::has(self::SELF_KEY)) {
            $this->status();
        }

        return Cache::get(self::SELF_KEY);
    }

    /**
     * @param  array<string, string>  $cookies  c_user, xs, datr
     * @return string|null error message, null on success
     */
    public function login(array $cookies): ?string
    {
        $start = $this->call('post', '/v3/login/start/messenger');
        if (! isset($start['login_id'], $start['step_id'])) {
            return data_get($start, 'error', 'Sild ei vastanud.');
        }

        $done = $this->call('post', "/v3/login/step/{$start['login_id']}/{$start['step_id']}/cookies", $cookies);

        if (data_get($done, 'type') === 'complete') {
            Cache::forget(self::SELF_KEY);
            $this->status();
            return null;
        }

        return data_get($done, 'error', 'Sisselogimine ebaõnnestus.');
    }

    public function logout(): void
    {
        $this->call('post', '/v3/logout/all');
        Cache::forget(self::SELF_KEY);
    }

    private function call(string $method, string $path, array $body = []): ?array
    {
        try {
            $response = Http::withToken((string) config('services.messenger.provision_secret'))
                ->timeout(60)
                ->{$method}(rtrim(config('services.messenger.provision_url'), '/') . $path
                    . '?user_id=' . rawurlencode((string) config('services.messenger.matrix_user')),
                    $method === 'get' ? null : (object) $body);
        } catch (Throwable) {
            return null;
        }

        return (array) $response->json() + ($response->successful() ? [] : ['error' => $response->json('error') ?? 'HTTP ' . $response->status()]);
    }
}
