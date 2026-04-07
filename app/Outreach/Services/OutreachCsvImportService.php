<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachLead;
use Illuminate\Support\Facades\DB;

/**
 * OutreachCsvImportService
 *
 * Imports leads from a CSV file into an outreach campaign.
 *
 * ── Supported columns ───────────────────────────────────────────────────────
 *   Required : email
 *   Optional : first_name, last_name, company, website, industry
 *
 * Missing optional columns are silently skipped — the import still runs.
 * Column order does not matter; matching is done by header name.
 *
 * ── Duplicate handling ──────────────────────────────────────────────────────
 * A duplicate is defined as the same (email, campaign_id) pair. Duplicates
 * are skipped — safe to re-import the same file multiple times.
 *
 * ── Initial lead state ──────────────────────────────────────────────────────
 *   status       = active
 *   current_step = 0
 *   replied      = false
 *   enrolled_at  = now()
 *   next_send_at = now()     (ready for first send on next scheduler run)
 *   ai_line      = null      (generated on first send if campaign.use_ai_line)
 */
class OutreachCsvImportService
{
    /**
     * Import leads from a CSV file into the given campaign.
     *
     * @param  string $filePath   Absolute path to the CSV file on disk.
     * @param  int    $campaignId Target campaign ID.
     * @return int    Number of leads actually inserted.
     *
     * @throws \InvalidArgumentException if the campaign does not exist or
     *                                   the file cannot be opened.
     */
    public function import(string $filePath, int $campaignId): int
    {
        $campaign = OutreachCampaign::find($campaignId);

        if (! $campaign) {
            throw new \InvalidArgumentException("Campaign #{$campaignId} not found.");
        }

        $handle = @fopen($filePath, 'r');

        if ($handle === false) {
            throw new \InvalidArgumentException("Cannot open CSV file: {$filePath}");
        }

        try {
            return $this->processFile($handle, $campaign);
        } finally {
            fclose($handle);
        }
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    /** @param resource $handle */
    private function processFile($handle, OutreachCampaign $campaign): int
    {
        // Read header row
        $headers = fgetcsv($handle);

        if ($headers === false || empty($headers)) {
            return 0;
        }

        // Normalise headers: trim whitespace and lowercase
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        // Build column index map: column_name => array_index
        $allowed = ['email', 'first_name', 'last_name', 'company', 'website', 'industry'];
        $colMap   = [];
        foreach ($allowed as $col) {
            $idx = array_search($col, $headers, true);
            if ($idx !== false) {
                $colMap[$col] = $idx;
            }
        }

        if (! isset($colMap['email'])) {
            throw new \InvalidArgumentException('CSV must contain an "email" column.');
        }

        // Load existing emails for this campaign to skip duplicates efficiently
        $existing = DB::table('outreach_leads')
            ->where('campaign_id', $campaign->id)
            ->pluck('email')
            ->map(fn($e) => strtolower(trim($e)))
            ->flip()  // use as a hash set for O(1) lookup
            ->all();

        $now      = now();
        $inserted = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            $email = strtolower(trim($row[$colMap['email']] ?? ''));

            // Validate email
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Skip duplicates
            if (isset($existing[$email])) {
                continue;
            }

            $lead = [
                'campaign_id'  => $campaign->id,
                'email'        => $email,
                'first_name'   => $this->col($row, $colMap, 'first_name') ?: 'Friend',
                'last_name'    => $this->col($row, $colMap, 'last_name'),
                'company'      => $this->col($row, $colMap, 'company'),
                'website'      => $this->col($row, $colMap, 'website'),
                'industry'     => $this->col($row, $colMap, 'industry'),
                'ai_line'      => null,
                'status'       => OutreachLead::STATUS_ACTIVE,
                'current_step' => 0,
                'replied'      => false,
                'enrolled_at'  => $now,
                'next_send_at' => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ];

            OutreachLead::create($lead);

            // Add to in-memory set so repeated emails within the same file are
            // also deduplicated without requiring a DB round-trip per row.
            $existing[$email] = true;

            $inserted++;
        }

        return $inserted;
    }

    /**
     * Extract and trim a column value from a CSV row.
     * Returns null if the column is absent or the value is empty.
     */
    private function col(array $row, array $colMap, string $name): ?string
    {
        if (! isset($colMap[$name])) {
            return null;
        }

        $value = trim($row[$colMap[$name]] ?? '');

        return $value !== '' ? $value : null;
    }
}
