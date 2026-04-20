<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AiPersonalizationService
 *
 * Generates a one-sentence personalisation line for a cold email lead
 * using the OpenAI Chat Completions API.
 *
 * ── Prompt resolution (priority order) ──────────────────────────────────────
 * 1. campaign.ai_prompt is set  → use it (after resolving {{placeholders}})
 * 2. campaign.ai_prompt is null → use the built-in default prompt
 *
 * Supported placeholders inside ai_prompt:
 *   {{company}}, {{website}}, {{industry}},
 *   {{first_name}}, {{last_name}}, {{email}}
 *
 * strtr() is used for placeholder replacement — a single pass with no risk
 * of a substituted value containing another placeholder.
 *
 * ── Responsibility boundary ──────────────────────────────────────────────────
 * This service only generates and persists the line. The decision of WHETHER
 * to call it (campaign.use_ai_line check, empty-guard) lives in the caller
 * (OutreachEmailService). generateLine() assumes the caller already determined
 * that a new line is needed.
 *
 * ── Persistence ─────────────────────────────────────────────────────────────
 * The result — including any fallback line — is ALWAYS saved to lead.ai_line.
 * This guarantees the API is called at most once per lead regardless of whether
 * the first attempt succeeded or fell back. The caller should $lead->refresh()
 * after calling generateLine() to see the persisted value.
 *
 * ── Isolation ───────────────────────────────────────────────────────────────
 * Entirely within App\Outreach. No dependency on legacy mail infrastructure.
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
     * Generate a personalisation line and save it to lead.ai_line.
     *
     * On API success  → saves the generated sentence.
     * On any failure  → saves a random fallback sentence.
     *
     * Either way lead.ai_line is populated after this call, so subsequent
     * steps will not reach this service again (caller guards on empty check).
     *
     * @return string  The line that was saved (for logging convenience).
     */
    public function generateLine(OutreachLead $lead, OutreachCampaign $campaign): string
    {
        $apiKey = config('services.openai.key');

        if (empty($apiKey)) {
            Log::warning('[Outreach] OpenAI API key not configured, using fallback line', [
                'lead_id'     => $lead->id,
                'campaign_id' => $campaign->id,
            ]);

            return $this->saveLine($lead, $this->randomFallback());
        }

        try {
            $line = $this->callOpenAi($apiKey, $lead, $campaign);
        } catch (\Throwable $e) {
            Log::warning('[Outreach] OpenAI request failed, using fallback line', [
                'lead_id'     => $lead->id,
                'campaign_id' => $campaign->id,
                'error'       => $e->getMessage(),
            ]);

            // Save the fallback — this prevents re-calling the (broken) API on
            // every subsequent step send for the same lead.
            return $this->saveLine($lead, $this->randomFallback());
        }

        return $this->saveLine($lead, $line);
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    private function saveLine(OutreachLead $lead, string $line): string
    {
        $lead->update(['ai_line' => $line]);
        return $line;
    }

    private function callOpenAi(string $apiKey, OutreachLead $lead, OutreachCampaign $campaign): string
    {
        $response = Http::withToken($apiKey)
            ->timeout(self::TIMEOUT)
            ->post(self::API_URL, [
                'model'       => self::MODEL,
                'max_tokens'  => 80,
                'temperature' => 0.7,
                'messages'    => [
                    ['role' => 'user', 'content' => $this->buildPrompt($lead, $campaign)],
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

        return trim(trim($content), '"\'');
    }

    /**
     * Build the final prompt string to send to OpenAI.
     *
     * If the campaign has a custom ai_prompt, it is used after resolving any
     * {{placeholder}} tokens with the lead's data. The output-format instruction
     * (plain text, no markdown) is always appended so the response is safe to
     * drop directly into an email body.
     *
     * If ai_prompt is null or empty, the built-in default prompt is used.
     *
     * ── Placeholder reference ────────────────────────────────────────────────
     *   {{company}}    → lead.company
     *   {{website}}    → lead.website
     *   {{industry}}   → lead.industry
     *   {{first_name}} → lead.first_name
     *   {{last_name}}  → lead.last_name
     *   {{email}}      → lead.email
     */
    private function buildPrompt(OutreachLead $lead, OutreachCampaign $campaign): string
    {
        if (! empty($campaign->ai_prompt)) {
            // Resolve lead-data placeholders inside the operator-supplied prompt.
            // strtr() performs all substitutions in a single pass — no risk of
            // a substituted value containing a placeholder that gets re-evaluated.
            $resolvedPrompt = strtr($campaign->ai_prompt, [
                '{{company}}'           => $lead->company           ?? '',
                '{{website}}'           => $lead->website           ?? '',
                '{{industry}}'          => $lead->industry          ?? '',
                '{{first_name}}'        => $lead->first_name        ?? '',
                '{{last_name}}'         => $lead->last_name         ?? '',
                '{{email}}'             => $lead->email,
                '{{performance_score}}' => $lead->performance_score !== null
                                            ? (string) $lead->performance_score
                                            : '',
                '{{lcp_mobile}}'        => $lead->lcp_mobile !== null
                                            ? (string) $lead->lcp_mobile
                                            : '',
            ]);

            // Append universal output-format constraints so the result is always
            // safe to embed directly in an email body. These constraints are
            // appended rather than embedded in the operator prompt so that
            // operators cannot accidentally omit them.
            return $resolvedPrompt . "\n\n" . $this->outputConstraints();
        }

        return $this->defaultPrompt($lead);
    }

    /**
     * Output-format constraints appended to every operator-supplied prompt.
     *
     * Rules are written as explicit instructions rather than examples so that
     * the model treats them as hard constraints, not soft guidelines.
     */
    private function outputConstraints(): string
    {
        return <<<CONSTRAINTS
Output requirements (strictly enforced):
- Write 1 to 2 sentences only.
- Plain text only. No markdown, no bullet points, no headers.
- Do not wrap the output in quotes.
- Do not include a greeting or sign-off.
- Output only the personalisation sentence(s), nothing else.
CONSTRAINTS;
    }

    /**
     * Default prompt used when campaign.ai_prompt is null/empty.
     *
     * Kept for full backward compatibility with campaigns created before the
     * ai_prompt field was introduced.
     */
    private function defaultPrompt(OutreachLead $lead): string
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
- Plain text only, no markdown
- Do not wrap the output in quotes
- Output only the sentence

PROMPT;
    }

    private function randomFallback(): string
    {
        return self::FALLBACK_LINES[array_rand(self::FALLBACK_LINES)];
    }
}
