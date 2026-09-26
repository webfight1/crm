<?php

namespace App\Seo\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin client for the SEO-monitor API (seo.webfight.ee/api/v1), authenticated
 * as an admin service user. Throws on failure — callers decide how to report.
 */
class SeoMonitorClient
{
    public function enabled(): bool
    {
        return filled(config('services.seo_monitor.api_url')) && filled(config('services.seo_monitor.token'));
    }

    /** Frontend link to a project. */
    public function projectUrl(int $id): string
    {
        return rtrim((string) config('services.seo_monitor.app_url'), '/') . "/projects/{$id}";
    }

    /** @return array<int, array> all projects (id, name, domain, google_connected …) */
    public function projects(): array
    {
        return $this->http()->get('projects')->throw()->json('data') ?? [];
    }

    /** false only when SEO-monitor says the project is gone (deleted there). */
    public function projectExists(int $id): bool
    {
        return $this->http()->get("projects/{$id}")->status() !== 404;
    }

    public function createProject(string $name, string $domain, string $url, ?string $gscProperty): int
    {
        return (int) $this->http()->post('projects', array_filter([
            'name' => $name, 'domain' => $domain, 'url' => $url, 'gsc_property' => $gscProperty,
        ]))->throw()->json('data.id');
    }

    /**
     * false = already tracked (or rejected); the project stays usable either way.
     * A target page SEO-monitor rejects doesn't cost the keyword: it's added without one.
     */
    public function addKeyword(int $projectId, string $keyword, ?string $targetUrl): bool
    {
        $r = $this->http()->post("projects/{$projectId}/keywords", array_filter([
            'keyword' => $keyword, 'target_url' => $targetUrl,
        ]));
        if ($targetUrl && $r->status() === 422 && $r->json('errors.target_url')) {
            $r = $this->http()->post("projects/{$projectId}/keywords", ['keyword' => $keyword]);
        }

        return $r->successful();
    }

    /** The project's site address, e.g. "https://klient.ee/" (null when unknown). */
    public function projectSite(int $projectId): ?string
    {
        return $this->http()->get("projects/{$projectId}")->json('data.url');
    }

    /**
     * A page URL in the project's form — same scheme + host as the project
     * (http→https, www or not), no query or fragment. SEO-monitor only takes
     * pages of its own site in exactly that form.
     */
    public static function onSite(string $url, ?string $site): string
    {
        $p = parse_url($url);
        $s = $site ? parse_url($site) : null;
        if (empty($p['host']) || empty($s['host'])
            || preg_replace('/^www\./i', '', strtolower($p['host'])) !== preg_replace('/^www\./i', '', strtolower($s['host']))
        ) {
            return strtok($url, '?#');
        }

        return ($s['scheme'] ?? 'https') . '://' . $s['host'] . (isset($s['port']) ? ':' . $s['port'] : '') . ($p['path'] ?? '/');
    }

    /** A page for PageSpeed tracking ("Lehed"); false = already there (or rejected). */
    public function addPage(int $projectId, string $url): bool
    {
        return $this->http()->post("projects/{$projectId}/pages", ['url' => $url])->successful();
    }

    /**
     * Client account with access to the project: an existing user (same e-mail)
     * gets the project added, otherwise a new "client" user with a random
     * password nobody knows — the client sets their own via the invite link.
     */
    public function grantClient(int $projectId, string $email, string $name): int
    {
        $existing = collect($this->http()->get('users')->throw()->json('data') ?? [])
            ->first(fn ($u) => strcasecmp((string) ($u['email'] ?? ''), $email) === 0);

        if ($existing) {
            $ids = collect($existing['projects'] ?? [])->pluck('id')->push($projectId)->unique()->values()->all();
            $this->http()->patch("users/{$existing['id']}", ['project_ids' => $ids])->throw();

            return (int) $existing['id'];
        }

        return (int) $this->http()->post('users', [
            'name' => $name, 'email' => $email, 'role' => 'client',
            'password' => Str::password(40), 'project_ids' => [$projectId],
        ])->throw()->json('data.id');
    }

    /**
     * One-time "set your password" link — same contract as OpHub's invites
     * (POST users/{id}/invite → {"invite_url": …}). null while SEO-monitor has
     * no invite endpoint yet, or when the user already accepted one (422).
     */
    public function inviteUrl(int $userId): ?string
    {
        $r = $this->http()->post("users/{$userId}/invite");
        if (in_array($r->status(), [404, 405, 422], true)) {
            return null;
        }

        return $r->throw()->json('invite_url');
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.seo_monitor.api_url'), '/') . '/')
            ->withToken((string) config('services.seo_monitor.token'))
            ->acceptJson()
            ->timeout(20);
    }
}
