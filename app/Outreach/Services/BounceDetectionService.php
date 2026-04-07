<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Models\OutreachLead;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * BounceDetectionService
 *
 * Scans each active inbox for Non-Delivery Reports (NDRs) via IMAP and
 * marks the corresponding leads as bounced.
 *
 * ── NDR identification ──────────────────────────────────────────────────────
 *
 * An NDR is identified by ALL THREE of the following:
 *   1. Sender is MAILER-DAEMON or postmaster (From header, case-insensitive)
 *   2. Subject contains "delivery" or "undeliverable" or "failed" or
 *      "returned mail" (case-insensitive)
 *   3. Body contains a parsable failed-recipient address that matches one of
 *      our active leads
 *
 * Matching strategy (in order of reliability):
 *   A. X-Failed-Recipients header     — set by many MTAs (Postfix, Exim)
 *   B. Final-Recipient DSN field       — RFC 3461 "message/delivery-status" part
 *   C. Original-Recipient DSN field    — RFC 3461 alternate
 *   D. "To:" inside the embedded original headers
 *
 * All extraction is done on the raw headers + body text. No MIME parsing
 * library is required — plain text search is sufficient for the narrow
 * patterns used by NDRs.
 *
 * ── What is NOT done ────────────────────────────────────────────────────────
 * Soft bounces (4xx / mailbox full / quota) are NOT marked as bounced because
 * they may be transient. Those are handled by the SMTP retry policy.
 */
class BounceDetectionService
{
    /** Subject keywords that strongly indicate an NDR (case-insensitive). */
    private const NDR_SUBJECT_KEYWORDS = [
        'undeliverable',
        'undelivered',
        'delivery failure',
        'delivery status',
        'returned mail',
        'mail delivery failed',
        'failure notice',
        'non-delivery',
    ];

    /** From patterns that identify an MTA bounce sender. */
    private const NDR_FROM_PATTERNS = [
        'mailer-daemon',
        'postmaster',
        'mail-delivery-subsystem',
    ];

    public function __construct(
        private readonly OutreachAuditService $audit,
        private readonly LoggerInterface      $logger,
    ) {}

    /**
     * Scan all active inboxes for NDRs.
     *
     * @return int  Total number of leads marked bounced.
     */
    public function checkAllAccounts(): int
    {
        $accounts = OutreachEmailAccount::where('is_active', true)
            ->whereNotNull('imap_host')
            ->get();

        $total = 0;
        foreach ($accounts as $account) {
            $total += $this->checkAccount($account);
        }

        return $total;
    }

    /**
     * Scan a single inbox for NDRs.
     *
     * @return int  Number of leads marked bounced.
     */
    public function checkAccount(OutreachEmailAccount $account): int
    {
        if (! extension_loaded('imap')) {
            $this->logger->error('[Outreach] PHP IMAP extension not loaded. Bounce detection skipped.');
            return 0;
        }

        try {
            $imap = $this->openImapConnection($account);
        } catch (Throwable $e) {
            $this->logger->error('[Outreach] IMAP connection failed for bounce detection', [
                'account' => $account->email,
                'error'   => $e->getMessage(),
            ]);
            return 0;
        }

        try {
            $detected = $this->detectBounces($imap, $account);
        } finally {
            imap_close($imap);
        }

        if ($detected > 0) {
            $this->logger->info('[Outreach] Bounce detection complete', [
                'account'  => $account->email,
                'bounced'  => $detected,
            ]);
        }

        return $detected;
    }

    // ─── IMAP Connection ────────────────────────────────────────────────────

    /** @return resource */
    private function openImapConnection(OutreachEmailAccount $account)
    {
        $encryption = strtolower($account->imap_encryption ?? 'ssl');

        $flags = match ($encryption) {
            'ssl'  => '/ssl',
            'tls'  => '/tls',
            'none' => '/novalidate-cert',
            default => '/ssl',
        };

        $mailbox = sprintf(
            '{%s:%d/imap%s}INBOX',
            $account->imap_host,
            $account->imap_port,
            $flags,
        );

        $imap = @imap_open($mailbox, $account->imap_username, $account->imap_password);

        if ($imap === false) {
            throw new \RuntimeException(
                'imap_open failed: ' . implode('; ', imap_errors() ?: ['unknown error'])
            );
        }

        return $imap;
    }

    // ─── Detection Logic ────────────────────────────────────────────────────

    /** @param resource $imap */
    private function detectBounces($imap, OutreachEmailAccount $account): int
    {
        // Fetch only UNSEEN messages to avoid re-processing on every run
        $msgNums = @imap_search($imap, 'UNSEEN');
        if (! $msgNums) {
            return 0;
        }

        // Build a map of email => lead for fast lookup
        $leadMap = $this->buildLeadMap($account);
        if (empty($leadMap)) {
            return 0;
        }

        $detected = 0;

        foreach ($msgNums as $msgNum) {
            $rawHeaders = @imap_fetchheader($imap, $msgNum);
            if (! $rawHeaders) {
                continue;
            }

            // Gate 1: must come from an MTA bounce sender
            if (! $this->isNdrSender($rawHeaders)) {
                continue;
            }

            // Gate 2: subject must look like an NDR
            if (! $this->isNdrSubject($rawHeaders)) {
                continue;
            }

            // Gate 3: extract the failed recipient from body/headers
            $body          = @imap_body($imap, $msgNum) ?: '';
            $failedAddress = $this->extractFailedRecipient($rawHeaders, $body);

            if (! $failedAddress) {
                continue;
            }

            $normalised = strtolower(trim($failedAddress));

            if (! isset($leadMap[$normalised])) {
                continue;
            }

            $lead = $leadMap[$normalised];

            if ($lead->status === OutreachLead::STATUS_BOUNCED) {
                continue; // already handled
            }

            $lead->markBounced();
            $detected++;

            $this->audit->bounced($lead->id, 'ndr_scan', "NDR for: {$failedAddress}");

            $this->logger->warning('[Outreach] Hard bounce detected via NDR', [
                'lead_id'        => $lead->id,
                'email'          => $lead->email,
                'msg_num'        => $msgNum,
                'failed_address' => $failedAddress,
            ]);
        }

        return $detected;
    }

    /**
     * Build a normalised email → lead map for active, non-bounced leads
     * assigned to this inbox.
     *
     * @return array<string, OutreachLead>
     */
    private function buildLeadMap(OutreachEmailAccount $account): array
    {
        $leads = OutreachLead::where('assigned_email_account_id', $account->id)
            ->whereNotIn('status', [OutreachLead::STATUS_BOUNCED])
            ->get();

        $map = [];
        foreach ($leads as $lead) {
            $map[strtolower(trim($lead->email))] = $lead;
        }
        return $map;
    }

    // ─── NDR Identification ─────────────────────────────────────────────────

    private function isNdrSender(string $rawHeaders): bool
    {
        $from = strtolower($this->extractHeader($rawHeaders, 'From'));
        foreach (self::NDR_FROM_PATTERNS as $pattern) {
            if (str_contains($from, $pattern)) {
                return true;
            }
        }
        return false;
    }

    private function isNdrSubject(string $rawHeaders): bool
    {
        $subject = strtolower($this->extractHeader($rawHeaders, 'Subject'));
        foreach (self::NDR_SUBJECT_KEYWORDS as $keyword) {
            if (str_contains($subject, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract the failed recipient's email address from the NDR.
     *
     * Tries four sources in order of specificity:
     *   1. X-Failed-Recipients header (Postfix, many cloud MTAs)
     *   2. Final-Recipient DSN field  (RFC 3461 delivery-status body)
     *   3. Original-Recipient header  (RFC 3461)
     *   4. "To:" header in the embedded original message headers
     */
    private function extractFailedRecipient(string $rawHeaders, string $body): ?string
    {
        // 1. X-Failed-Recipients: user@example.com
        $value = $this->extractHeader($rawHeaders, 'X-Failed-Recipients');
        if ($address = $this->parseEmailAddress($value)) {
            return $address;
        }

        // 2. Final-Recipient: rfc822; user@example.com
        if (preg_match('/Final-Recipient\s*:\s*rfc822\s*;\s*(\S+)/i', $body, $m)) {
            if ($address = $this->parseEmailAddress($m[1])) {
                return $address;
            }
        }

        // 3. Original-Recipient: rfc822; user@example.com
        if (preg_match('/Original-Recipient\s*:\s*rfc822\s*;\s*(\S+)/i', $body, $m)) {
            if ($address = $this->parseEmailAddress($m[1])) {
                return $address;
            }
        }

        // 4. To: inside the bounced original headers block
        // NDRs commonly embed "-----Original Message-----" or "--- below this line ---"
        // with the original headers. We look for a "To:" line in the body.
        if (preg_match('/^To:\s*(.+)$/im', $body, $m)) {
            if ($address = $this->parseEmailAddress($m[1])) {
                return $address;
            }
        }

        return null;
    }

    /**
     * Extract a bare email address from a string that may contain a display
     * name ("Foo Bar <foo@example.com>") or angle brackets ("<foo@example.com>").
     */
    private function parseEmailAddress(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Angle-bracket form: "Name <email>" or just "<email>"
        if (preg_match('/<([^>@\s]+@[^>@\s]+)>/', $value, $m)) {
            return strtolower(trim($m[1]));
        }

        // Bare address
        if (preg_match('/([^\s<>,;]+@[^\s<>,;]+)/', $value, $m)) {
            return strtolower(trim($m[1], '<>.,;'));
        }

        return null;
    }

    // ─── Header Helper ───────────────────────────────────────────────────────

    private function extractHeader(string $rawHeaders, string $name): string
    {
        $pattern = '/^' . preg_quote($name, '/') . ':\s*(.+(?:\r?\n[ \t].+)*)/im';
        if (preg_match($pattern, $rawHeaders, $m)) {
            return preg_replace('/\r?\n[ \t]+/', ' ', trim($m[1]));
        }
        return '';
    }
}
