<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ClickUpService
 *
 * Thin wrapper around the ClickUp API v2 (https://developer.clickup.com/reference).
 * Authenticates with a personal API token (pk_...) from CLICKUP_API_TOKEN.
 *
 * The only thing we need from ClickUp today is "give me the rows of a list /
 * view so we can pull company name + email out of them" — so this class stays
 * deliberately small: fetch tasks, then normalise each task into a flat row.
 *
 * ── Where the IDs come from ─────────────────────────────────────────────────
 * A ClickUp URL like
 *     https://app.clickup.com/9015331367/v/cn/8cnp2h7-1595
 *                             ^team id      ^view id
 * gives you the view id as the last segment. List URLs (/v/li/<id>) give a
 * list id instead — both are supported (tasksFromView / tasksFromList).
 *
 * ── Where the email lives ───────────────────────────────────────────────────
 * ClickUp has no fixed "email" column; it is nearly always a custom field.
 * extractRow() therefore looks, in order:
 *   1. a custom field of type `email`
 *   2. a custom field whose name looks like e-mail / meil / kontakt
 *   3. the first email-shaped string in the task name / description
 */
class ClickUpService
{
    private const BASE = 'https://api.clickup.com/api/v2';

    public function __construct(
        private readonly ?string $token = null,
    ) {
    }

    private function client(): PendingRequest
    {
        $token = $this->token ?: config('services.clickup.token');

        if (blank($token)) {
            throw new RuntimeException(
                'CLICKUP_API_TOKEN puudub. Lisa see .env faili (ClickUp → Settings → Apps → API Token).'
            );
        }

        return Http::withHeaders([
                'Authorization' => $token,
                'Content-Type'  => 'application/json',
            ])
            ->timeout(30)
            ->retry(3, 500, throw: false)
            ->baseUrl(self::BASE);
    }

    /** Workspaces the token can see — handy as a connectivity smoke test. */
    public function teams(): array
    {
        return $this->get('/team')['teams'] ?? [];
    }

    /** Metadata for a single view (name, type, parent list, ...). */
    public function view(string $viewId): array
    {
        return $this->get("/view/{$viewId}")['view'] ?? [];
    }

    /** Views available on a list, grouped by type. */
    public function listViews(string $listId): array
    {
        return $this->get("/list/{$listId}/view");
    }

    /**
     * Every task visible in a view, following pagination to the end.
     *
     * @return array<int, array<string, mixed>> raw ClickUp task payloads
     */
    public function tasksFromView(string $viewId): array
    {
        return $this->paginate(fn (int $page) => $this->get("/view/{$viewId}/task", ['page' => $page]));
    }

    /**
     * Every task in a list, following pagination to the end.
     *
     * @param  bool $includeClosed  ClickUp hides closed tasks unless asked.
     * @return array<int, array<string, mixed>> raw ClickUp task payloads
     */
    public function tasksFromList(string $listId, bool $includeClosed = true): array
    {
        return $this->paginate(fn (int $page) => $this->get("/list/{$listId}/task", [
            'page'           => $page,
            'include_closed' => $includeClosed ? 'true' : 'false',
            'subtasks'       => 'true',
        ]));
    }

    /**
     * Flatten a raw ClickUp task into the fields we actually care about.
     *
     * @param  array<string, mixed> $task
     * @return array{id: string, company: string, email: ?string, status: ?string, url: ?string, website: ?string, custom: array<string, mixed>}
     */
    public function extractRow(array $task): array
    {
        $custom = [];

        foreach ($task['custom_fields'] ?? [] as $field) {
            $name = $field['name'] ?? null;
            if ($name === null) {
                continue;
            }
            $custom[$name] = $this->customFieldValue($field);
        }

        return [
            'id'      => (string) ($task['id'] ?? ''),
            'company' => $this->pickCompany($task, $custom),
            'email'   => $this->pickEmail($task, $custom),
            'website' => $this->pickByName($custom, '/(veeb|website|url|koduleht|domain)/i'),
            'status'  => $task['status']['status'] ?? null,
            'url'     => $task['url'] ?? null,
            'custom'  => $custom,
        ];
    }

    // ── internals ───────────────────────────────────────────────────────────

    /** @param callable(int): array $fetch */
    private function paginate(callable $fetch): array
    {
        $tasks = [];
        $page  = 0;

        do {
            $body = $fetch($page);
            $batch = $body['tasks'] ?? [];
            $tasks = array_merge($tasks, $batch);
            $page++;
            // ClickUp returns last_page on view endpoints; list endpoints just
            // return a short/empty page. Guard against runaway loops either way.
        } while ($batch !== [] && ($body['last_page'] ?? false) !== true && $page < 100);

        return $tasks;
    }

    private function get(string $path, array $query = []): array
    {
        $response = $this->client()->get($path, $query);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'ClickUp API %s → HTTP %d: %s',
                $path,
                $response->status(),
                mb_substr((string) $response->body(), 0, 300)
            ));
        }

        return $response->json() ?? [];
    }

    /** ClickUp encodes each custom field type differently; normalise to a scalar. */
    private function customFieldValue(array $field): mixed
    {
        $value = $field['value'] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return match ($field['type'] ?? '') {
            // drop_down stores the option index; resolve it to its label
            'drop_down' => collect($field['type_config']['options'] ?? [])
                ->firstWhere('orderindex', is_numeric($value) ? (int) $value : $value)['name'] ?? $value,
            'labels' => collect($field['type_config']['options'] ?? [])
                ->whereIn('id', (array) $value)
                ->pluck('label')
                ->implode(', '),
            'users' => collect((array) $value)->pluck('email')->filter()->implode(', '),
            'date'  => is_numeric($value) ? date('Y-m-d', (int) $value / 1000) : $value,
            default => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
        };
    }

    private function pickCompany(array $task, array $custom): string
    {
        return (string) ($this->pickByName($custom, '/(firma|ettev|company|klient|customer|nimi)/i')
            ?: ($task['name'] ?? ''));
    }

    private function pickEmail(array $task, array $custom): ?string
    {
        // 1. a real email-typed custom field
        foreach ($task['custom_fields'] ?? [] as $field) {
            if (($field['type'] ?? '') === 'email' && filled($field['value'] ?? null)) {
                return trim((string) $field['value']);
            }
        }

        // 2. a custom field that is named like an email column
        if ($named = $this->pickByName($custom, '/(e-?mail|meil|kontakt)/i')) {
            if ($found = $this->firstEmailIn((string) $named)) {
                return $found;
            }
        }

        // 3. anything email-shaped in the free text of the task
        return $this->firstEmailIn(implode(' ', [
            $task['name'] ?? '',
            $task['text_content'] ?? $task['description'] ?? '',
        ]));
    }

    /** First custom field whose *name* matches the pattern. */
    private function pickByName(array $custom, string $pattern): ?string
    {
        foreach ($custom as $name => $value) {
            if (filled($value) && preg_match($pattern, (string) $name)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function firstEmailIn(string $text): ?string
    {
        if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/u', $text, $m)) {
            return rtrim($m[0], '.');
        }

        return null;
    }
}
