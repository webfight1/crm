<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PageSpeedService
 *
 * Fetches Google PageSpeed Insights data for a lead's website and stores
 * the result back on the lead row so it can be used in email templates.
 *
 * Stored fields:
 *   lead.performance_score  — 0–100 Google Lighthouse performance score
 *   lead.lcp_mobile         — Largest Contentful Paint in seconds (mobile)
 *
 * Template placeholders:
 *   {{performance_score}}   — e.g. "34"
 *   {{lcp_mobile}}          — e.g. "4.2"
 *
 * API key:
 *   Set PAGESPEED_API_KEY in .env. Without a key the API still works but
 *   limits you to ~1 request per second. With a free key the quota is
 *   25,000 requests per day.
 *
 *   Get a key: https://console.cloud.google.com → APIs → PageSpeed Insights API
 */
class PageSpeedService
{
    private const API_URL = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    private const TIMEOUT = 30; // seconds — PageSpeed can be slow

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Measure a single lead's website and save results.
     *
     * Returns an array with the measured values, or null if the lead has
     * no website or the request failed.
     *
     * @return array{performance_score: int, lcp_mobile: float, url: string}|null
     */
    public function measure(OutreachLead $lead): ?array
    {
        $url = $this->normalizeUrl($lead->website);

        if (! $url) {
            return null;
        }

        try {
            $data = $this->fetch($url);
        } catch (\Throwable $e) {
            Log::warning('[PageSpeed] Request failed', [
                'lead_id' => $lead->id,
                'url'     => $url,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }

        if (! $data) {
            return null;
        }

        $lead->update([
            'performance_score' => $data['performance_score'],
            'lcp_mobile'        => $data['lcp_mobile'],
        ]);

        return $data;
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    /**
     * Call the PageSpeed Insights API and extract the two key metrics.
     *
     * @return array{performance_score: int, lcp_mobile: float, url: string}|null
     */
    private function fetch(string $url): ?array
    {
        $params = [
            'url'      => $url,
            'strategy' => 'mobile',        // mobile scores are lower — more impactful for sales
            'category' => 'performance',
        ];

        $apiKey = config('services.pagespeed.key');
        if (! empty($apiKey)) {
            $params['key'] = $apiKey;
        }

        $response = Http::timeout(self::TIMEOUT)->get(self::API_URL, $params);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "PageSpeed API returned HTTP {$response->status()}: " . $response->body()
            );
        }

        $json = $response->json();

        // Performance score: 0.0–1.0 → round to integer 0–100
        $score = $json['lighthouseResult']['categories']['performance']['score'] ?? null;
        if ($score === null) {
            return null;
        }

        // LCP in milliseconds → convert to seconds, 1 decimal
        $lcpMs = $json['lighthouseResult']['audits']['largest-contentful-paint']['numericValue'] ?? null;
        $lcp   = $lcpMs !== null ? round($lcpMs / 1000, 1) : null;

        return [
            'performance_score' => (int) round($score * 100),
            'lcp_mobile'        => (float) ($lcp ?? 0),
            'url'               => $url,
        ];
    }

    /**
     * Ensure the URL has a scheme. Returns null if website is empty/unusable.
     */
    private function normalizeUrl(?string $website): ?string
    {
        if (empty($website)) {
            return null;
        }

        $website = trim($website);

        // Add https:// if no scheme present
        if (! preg_match('#^https?://#i', $website)) {
            $website = 'https://' . $website;
        }

        // Basic sanity check
        if (! filter_var($website, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $website;
    }
}
