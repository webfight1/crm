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
    private const SELF_NAME_KEY = 'chats.messenger.self_name';
    private const STATUS_KEY = 'chats.messenger.status';

    /** Bridge states meaning Meta dropped the session — only a fresh cookie login helps. */
    private const LOGGED_OUT_STATES = ['BAD_CREDENTIALS', 'LOGGED_OUT'];

    public function enabled(): bool
    {
        return filled(config('services.messenger.provision_secret')) && filled(config('services.messenger.hs_token'));
    }

    /** @return array{reachable: bool, loggedIn: bool, loggedOut: bool, name: ?string, state: ?string, message: ?string} */
    public function status(): array
    {
        $who = $this->call('get', '/v3/whoami');
        $login = data_get($who, 'logins.0');
        if ($login) {
            Cache::forever(self::SELF_KEY, (string) $login['id']);
            if ($name = data_get($login, 'name') ?: data_get($login, 'profile.name')) {
                Cache::forever(self::SELF_NAME_KEY, $name);
            }
        }

        $state = data_get($login, 'state.state_event');
        $status = [
            'reachable' => $who !== null,
            'loggedIn'  => (bool) $login,
            'loggedOut' => in_array($state, self::LOGGED_OUT_STATES, true),
            'name'      => data_get($login, 'name') ?: data_get($login, 'profile.name'),
            'state'     => $state,
            'message'   => data_get($login, 'state.message'),
        ];
        Cache::put(self::STATUS_KEY, $status, now()->addMinutes(5));

        return $status;
    }

    /** Warning for the chat pages when Messenger stopped delivering, else null. Cached 5 min. */
    public function problem(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }
        $status = Cache::get(self::STATUS_KEY) ?? $this->status();

        return match (true) {
            ! $status['reachable'] => 'Messengeri sild ei vasta — uusi Messengeri sõnumeid ei tule.',
            $status['loggedOut']   => 'Messenger on välja logitud (Meta tühistas sessiooni) — uusi sõnumeid ei tule. Logi uuesti sisse.',
            default                => null,
        };
    }

    /** Our own Facebook id — messages from its ghost are the ones we sent from the phone. */
    public function selfId(): ?string
    {
        if (! Cache::has(self::SELF_KEY)) {
            $this->status();
        }

        return Cache::get(self::SELF_KEY);
    }

    public function selfName(): ?string
    {
        return Cache::get(self::SELF_NAME_KEY);
    }

    /**
     * @param  array<string, string>  $cookies  c_user, xs, datr
     * @return string|null error message, null on success
     */
    public function login(array $cookies, string $site = 'facebook'): ?string
    {
        // c_user is our own Facebook id. Known before the bridge creates any
        // portal, so the first room events already tell our ghost from theirs.
        Cache::forever(self::SELF_KEY, (string) $cookies['c_user']);

        // Flow ids of mautrix-meta: "facebook" (facebook.com cookies) or "messenger" (messenger.com).
        $start = $this->call('post', '/v3/login/start/' . ($site === 'messenger' ? 'messenger' : 'facebook'));
        if (! isset($start['login_id'], $start['step_id'])) {
            return data_get($start, 'error', 'Sild ei vastanud.');
        }

        $done = $this->call('post', "/v3/login/step/{$start['login_id']}/{$start['step_id']}/cookies", $cookies);

        if (data_get($done, 'type') === 'complete') {
            Cache::forget(self::STATUS_KEY);
            $this->status();
            return null;
        }

        return match (data_get($done, 'errcode')) {
            'FI.MAU.META_TOKEN_ERROR' => 'Meta ei tunnustanud küpsiseid (sessioon pole sisse logitud). Kopeeri need uuesti samalt saidilt, kus oled sisse logitud, ja vali õige sait.',
            default => data_get($done, 'error', 'Sisselogimine ebaõnnestus.'),
        };
    }

    public function logout(): void
    {
        $this->call('post', '/v3/logout/all');
        Cache::forget(self::SELF_KEY);
        Cache::forget(self::STATUS_KEY);
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
