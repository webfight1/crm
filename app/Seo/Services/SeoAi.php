<?php

namespace App\Seo\Services;

use App\Seo\Playbook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal OpenAI JSON-mode client for the SEO pipeline. Model comes from the
 * Playbook (ai.model) so it can be switched without a deploy. Returns null on
 * any failure — every caller has a non-AI fallback.
 */
class SeoAi
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    public function enabled(): bool
    {
        return filled(config('services.openai.key'));
    }

    /** @return array<string, mixed>|null */
    public function json(string $system, string $user, int $maxTokens = 900): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post(self::API_URL, [
                    'model'           => Playbook::get('ai.model') ?: 'gpt-4o-mini',
                    'temperature'     => 0.3,
                    'max_tokens'      => $maxTokens,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user',   'content' => $user],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('[SeoAi] request failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            Log::warning('[SeoAi] HTTP ' . $response->status(), ['body' => mb_substr($response->body(), 0, 300)]);
            return null;
        }

        $data = json_decode((string) data_get($response->json(), 'choices.0.message.content'), true);

        return is_array($data) ? $data : null;
    }
}
