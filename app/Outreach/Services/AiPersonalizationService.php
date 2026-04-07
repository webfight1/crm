<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AiPersonalizationService
 *
 * Generates a one-sentence personalisation line for a cold email lead
 * using the OpenAI Chat Completions API.
 *
 * ── Caching strategy ────────────────────────────────────────────────────────
 * The generated line is stored on the lead (outreach_leads.ai_line) after the
 * first call. OutreachEmailService checks for an existing value before calling
 * this service, so each lead is only billed once per campaign.
 *
 * ── Failsafe ────────────────────────────────────────────────────────────────
 * If the OpenAI request fails for any reason (network error, quota exceeded,
 * bad response) a random fallback line is returned. The fallback is NOT saved
 * to the lead so the next send attempt will try the API again.
 *
 * ── Isolation ───────────────────────────────────────────────────────────────
 * This service is entirely within App\Outreach and has no dependency on the
 * existing legacy mail or CRM infrastructure.
 */
class AiPersonalizationService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL   = 'gpt-4o-mini';
    private const TIMEOUT = 15; // seconds

    private const FALLBACK_LINES = [
        'Your checkout flow could be simplified.',
        'Your store might convert better with small UX tweaks.',
        'Mobile experience could be improved for better results.',
        'A few layout changes could boost your conversion rate.',
        'Your product pages might benefit from clearer CTAs.',
    ];

    /**
     * Generate (or reuse) a personalisation line for the given lead.
     *
     * Saves the result to lead.ai_line on success so future sends reuse it.
     * Returns a fallback line on API failure without saving.
     */
    public function generateLine(OutreachLead $lead): string
    {
        // Already generated — reuse without hitting the API again
        if (! empty($lead->ai_line)) {
            return $lead->ai_line;
        }

        $apiKey = config('services.openai.key');

        if (empty($apiKey)) {
            Log::warning('[Outreach] OpenAI API key not configured. Using fallback AI line.', [
                'lead_id' => $lead->id,
            ]);
            return $this->fallback();
        }

        try {
            $line = $this->callOpenAi($apiKey, $lead);
        } catch (\Throwable $e) {
            Log::warning('[Outreach] OpenAI request failed, using fallback line', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
            return $this->fallback();
        }

        // Persist so subsequent steps reuse this line at zero API cost
        $lead->update(['ai_line' => $line]);

        return $line;
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    private function callOpenAi(string $apiKey, OutreachLead $lead): string
    {
        $prompt = $this->buildPrompt($lead);

        $response = Http::withToken($apiKey)
            ->timeout(self::TIMEOUT)
            ->post(self::API_URL, [
                'model'       => self::MODEL,
                'max_tokens'  => 60,
                'temperature' => 0.7,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                'OpenAI returned HTTP ' . $response->status() . ': ' . $response->body()
            );
        }

        $content = $response->json('choices.0.message.content');

        if (empty($content)) {
            throw new \RuntimeException('OpenAI response contained no content.');
        }

        // Strip surrounding quotes / whitespace that models sometimes add
        return trim(trim($content), '"\'');
    }

    private function buildPrompt(OutreachLead $lead): string
    {
        return <<<PROMPT
Write ONE short sentence for a cold email.

Company: {$lead->company}
Website: {$lead->website}
Industry: {$lead->industry}

We help improve e-commerce stores (Bagisto, WooCommerce).

Rules:
- Max 15 words
- Casual tone
- No buzzwords
- No emojis
- No exclamation marks

Output only the sentence.
PROMPT;
    }

    private function fallback(): string
    {
        return self::FALLBACK_LINES[array_rand(self::FALLBACK_LINES)];
    }
}
