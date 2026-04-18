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
 *   Optional : first_name, last_name, company, website, industry,
 *              lcp_mobile, performance_score, notes, qualification
 *   Special  : custom_line — if present and non-empty, its value is stored as
 *              ai_line verbatim, bypassing the OpenAI generation entirely.
 *              Useful when you already have personalisation copy in the CSV.
 *
 *   qualification     — 'lead' (default) or 'skip'. Skipped rows are still
 *                       imported but ignored by the send pipeline.
 *   performance_score — integer 0-100, clamped.
 *   lcp_mobile        — free-form string (e.g. "2.5s") rendered as {{lcp}}.
 *
 * Column order does not matter; matching is done by header name.
 * Missing optional columns are silently skipped.
 *
 * ── BOM handling ────────────────────────────────────────────────────────────
 * UTF-8 files exported from Excel/Sheets often begin with a byte-order mark
 * (0xEF 0xBB 0xBF). fgetcsv() treats the BOM as part of the first field
 * value, which causes the "email" header to not be found. We strip it.
 *
 * ── Duplicate handling ──────────────────────────────────────────────────────
 * Duplicates are defined as the same (campaign_id, email) pair.
 * The database has a UNIQUE index on this pair. We use insertOrIgnore()
 * in batches, which silently skips conflicting rows at the DB level.
 * This is race-condition safe — no gap between check and insert.
 *
 * ── Batch insert ────────────────────────────────────────────────────────────
 * Rows are collected and inserted in chunks of BATCH_SIZE (250).
 * This avoids per-row Eloquent overhead on large files.
 *
 * ── Initial lead state ──────────────────────────────────────────────────────
 *   status       = active
 *   current_step = 0
 *   replied      = false
 *   enrolled_at  = now()
 *   next_send_at = now()
 *   ai_line      = null (or custom_line value if provided)
 */
class OutreachCsvImportService
{
    private const BATCH_SIZE = 250;

    /** UTF-8 byte-order mark */
    private const BOM = "\xEF\xBB\xBF";

    /**
     * Import leads from a CSV file into the given campaign.
     *
     * @param  string $filePath   Absolute path to the CSV file.
     * @param  int    $campaignId Target campaign.
     * @return int    Number of rows handed to insertOrIgnore() (includes skipped dupes).
     *                Actual DB inserts may be lower if duplicates were silently skipped.
     *
     * @throws \InvalidArgumentException  if campaign not found or file unreadable.
     */
    public function import(string $filePath, int $campaignId): int
    {
        if (! OutreachCampaign::where('id', $campaignId)->exists()) {
            throw new \InvalidArgumentException("Campaign #{$campaignId} not found.");
        }

        $handle = @fopen($filePath, 'r');

        if ($handle === false) {
            throw new \InvalidArgumentException("Cannot open CSV file: {$filePath}");
        }

        try {
            return $this->processFile($handle, $campaignId);
        } finally {
            fclose($handle);
        }
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    /** @param resource $handle */
    private function processFile($handle, int $campaignId): int
    {
        $rawHeaders = fgetcsv($handle);

        if ($rawHeaders === false || empty($rawHeaders)) {
            return 0;
        }

        // Strip UTF-8 BOM from the first field (common in Excel-exported CSVs)
        if (str_starts_with($rawHeaders[0], self::BOM)) {
            $rawHeaders[0] = substr($rawHeaders[0], strlen(self::BOM));
        }

        // Normalise: lowercase + trim
        $headers = array_map(fn($h) => strtolower(trim($h)), $rawHeaders);

        // Map column names to their array index
        $colMap = [];
        foreach ([
            'email', 'first_name', 'last_name',
            'company', 'website', 'industry',
            'lcp_mobile', 'performance_score', 'notes', 'qualification',
            'custom_line',
        ] as $col) {
            $idx = array_search($col, $headers, true);
            if ($idx !== false) {
                $colMap[$col] = $idx;
            }
        }

        if (! isset($colMap['email'])) {
            throw new \InvalidArgumentException(
                'CSV must contain an "email" column. Headers found: ' . implode(', ', $headers)
            );
        }

        $now      = now();
        $batch    = [];
        $queued   = 0;

        while (($row = fgetcsv($handle)) !== false) {
            // Skip completely empty rows
            if (empty(array_filter($row, fn($v) => trim($v) !== ''))) {
                continue;
            }

            $email = strtolower(trim($row[$colMap['email']] ?? ''));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            // Normalise qualification: accept only 'lead' or 'skip', default 'lead'
            $qualRaw = strtolower((string) $this->col($row, $colMap, 'qualification'));
            $qualification = $qualRaw === OutreachLead::QUALIFICATION_SKIP
                ? OutreachLead::QUALIFICATION_SKIP
                : OutreachLead::QUALIFICATION_LEAD;

            // Performance score: integer 0-100 or null
            $perfRaw = $this->col($row, $colMap, 'performance_score');
            $performanceScore = is_numeric($perfRaw)
                ? max(0, min(100, (int) $perfRaw))
                : null;

            $batch[] = [
                'campaign_id'       => $campaignId,
                'email'             => $email,
                'first_name'        => $this->col($row, $colMap, 'first_name') ?: 'Friend',
                'last_name'         => $this->col($row, $colMap, 'last_name'),
                'company'           => $this->col($row, $colMap, 'company'),
                'website'           => $this->col($row, $colMap, 'website'),
                'industry'          => $this->col($row, $colMap, 'industry'),
                'lcp_mobile'        => $this->col($row, $colMap, 'lcp_mobile'),
                'performance_score' => $performanceScore,
                'notes'             => $this->col($row, $colMap, 'notes'),
                'qualification'     => $qualification,
                // custom_line in the CSV pre-fills ai_line, skipping OpenAI generation
                'ai_line'           => $this->col($row, $colMap, 'custom_line'),
                'status'            => OutreachLead::STATUS_ACTIVE,
                'current_step'      => 0,
                'replied'           => 0,   // raw int for DB::table insert
                'enrolled_at'       => $now,
                'next_send_at'      => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];

            $queued++;

            if (count($batch) >= self::BATCH_SIZE) {
                $this->flush($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            $this->flush($batch);
        }

        return $queued;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function flush(array $rows): void
    {
        // insertOrIgnore silently skips rows that violate the
        // UNIQUE(campaign_id, email) index — no exception thrown.
        DB::table('outreach_leads')->insertOrIgnore($rows);
    }

    private function col(array $row, array $colMap, string $name): ?string
    {
        if (! isset($colMap[$name])) {
            return null;
        }

        $value = trim($row[$colMap[$name]] ?? '');

        return $value !== '' ? $value : null;
    }
}
