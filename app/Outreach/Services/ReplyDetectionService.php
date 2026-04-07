<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachSendLog;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * ReplyDetectionService
 *
 * Connects to each active inbox via IMAP and checks whether any
 * lead has replied. Uses two complementary strategies:
 *
 *  Strategy A — Message-ID thread matching:
 *    Fetches raw headers for each UNSEEN message and checks whether the
 *    "In-Reply-To" or "References" header contains a Message-ID we sent.
 *    Never marks replied based on body text. Requires imap_fetchheader().
 *
 *  Strategy B — Sender-address fallback:
 *    For any lead not caught by Strategy A, search INBOX FROM the lead's
 *    email address SINCE the first send date. Results are then verified:
 *    the matching message must contain a reply-thread header (In-Reply-To
 *    or References) OR a reply-like subject prefix ("Re:" / "RE:").
 *    Messages from automated senders (MAILER-DAEMON, postmaster, no-reply,
 *    auto-reply, out-of-office) are excluded.
 *
 * Requires PHP IMAP extension (php-imap).
 */
class ReplyDetectionService
{
    /**
     * Subject prefixes that indicate a genuine human reply.
     * Intentionally a small, high-precision list.
     */
    private const REPLY_SUBJECT_PREFIXES = ['re:', 're :', 'aw:', 'sv:', 'vs:'];

    /**
     * From-address / From-name patterns that indicate automated mail.
     * Any match → skip the message, never count as a reply.
     */
    private const AUTOMATED_SENDER_PATTERNS = [
        'mailer-daemon',
        'postmaster',
        'no-reply',
        'noreply',
        'do-not-reply',
        'donotreply',
        'auto-reply',
        'autoreply',
        'mail-delivery',
        'delivery-status',
        'delivery status',
    ];

    public function __construct(
        private readonly OutreachAuditService $audit,
        private readonly LoggerInterface      $logger,
    ) {}

    /**
     * Check all active accounts for replies.
     *
     * @return int  Total number of new replies detected.
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
     * Check a single inbox for replies from its leads.
     *
     * @return int  Number of replies detected for this inbox.
     */
    public function checkAccount(OutreachEmailAccount $account): int
    {
        if (! extension_loaded('imap')) {
            $this->logger->error('[Outreach] PHP IMAP extension not loaded. Reply detection skipped.');
            return 0;
        }

        try {
            $imap = $this->openImapConnection($account);
        } catch (Throwable $e) {
            $this->logger->error('[Outreach] IMAP connection failed', [
                'account' => $account->email,
                'error'   => $e->getMessage(),
            ]);
            return 0;
        }

        try {
            $detected = $this->detectReplies($imap, $account);
        } finally {
            imap_close($imap);
        }

        $this->logger->info('[Outreach] Reply detection complete', [
            'account'  => $account->email,
            'detected' => $detected,
        ]);

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

        $imap = @imap_open(
            $mailbox,
            $account->imap_username,
            $account->imap_password,
        );

        if ($imap === false) {
            throw new \RuntimeException(
                'imap_open failed: ' . implode('; ', imap_errors() ?: ['unknown error'])
            );
        }

        return $imap;
    }

    // ─── Detection Logic ────────────────────────────────────────────────────

    /** @param resource $imap */
    private function detectReplies($imap, OutreachEmailAccount $account): int
    {
        $detected = 0;

        $leads = OutreachLead::where('assigned_email_account_id', $account->id)
            ->where('replied', false)
            ->where('status', OutreachLead::STATUS_ACTIVE)
            ->whereHas('sendLogs', fn($q) => $q->where('status', OutreachSendLog::STATUS_SENT))
            ->with(['sendLogs' => fn($q) => $q->where('status', OutreachSendLog::STATUS_SENT)
                                              ->orderBy('sent_at')])
            ->get();

        if ($leads->isEmpty()) {
            return 0;
        }

        // Strategy A: header-based Message-ID matching
        $detected += $this->detectByMessageId($imap, $leads);

        // Strategy B: sender-address search for leads not yet caught
        $remaining = $leads->filter(fn($l) => ! $l->replied && $l->status === OutreachLead::STATUS_ACTIVE);
        $detected += $this->detectBySenderAddress($imap, $remaining);

        return $detected;
    }

    // ─── Strategy A ─────────────────────────────────────────────────────────

    /**
     * Fetch headers of UNSEEN messages and verify In-Reply-To / References
     * against our sent Message-IDs. Never touches message bodies.
     *
     * @param resource $imap
     */
    private function detectByMessageId($imap, Collection $leads): int
    {
        // Build flat map: message_id => lead
        $messageIdMap = [];
        foreach ($leads as $lead) {
            foreach ($lead->sendLogs as $log) {
                if ($log->message_id) {
                    $messageIdMap[$log->message_id] = $lead;
                }
            }
        }

        if (empty($messageIdMap)) {
            return 0;
        }

        // Search for messages from the last 7 days (both UNSEEN and SEEN)
        // This allows reply detection to work even if user reads emails before cron runs
        $sevenDaysAgo = date('d-M-Y', strtotime('-7 days'));
        $messageNums = @imap_search($imap, "SINCE \"$sevenDaysAgo\"");
        if (! $messageNums) {
            return 0;
        }

        $detected = 0;

        foreach ($messageNums as $msgNum) {
            // imap_fetchheader returns raw RFC 2822 header block only — no body
            $rawHeaders = @imap_fetchheader($imap, $msgNum);
            if (! $rawHeaders) {
                continue;
            }

            // Skip automated senders before doing any further work
            if ($this->isAutomatedSender($rawHeaders)) {
                continue;
            }

            // Extract thread-reference headers
            $inReplyTo  = $this->extractHeader($rawHeaders, 'In-Reply-To');
            $references = $this->extractHeader($rawHeaders, 'References');

            // Check each sent Message-ID against thread headers
            foreach ($messageIdMap as $sentMessageId => $lead) {
                if ($lead->replied) {
                    continue;
                }

                if ($this->headerContainsMessageId($inReplyTo, $sentMessageId)
                    || $this->headerContainsMessageId($references, $sentMessageId)
                ) {
                    $lead->markReplied();
                    $detected++;

                    $this->audit->replyDetected($lead->id, 'message_id', $sentMessageId);

                    $this->logger->info('[Outreach] Reply detected via Message-ID header', [
                        'lead_id'    => $lead->id,
                        'email'      => $lead->email,
                        'message_id' => $sentMessageId,
                        'msg_num'    => $msgNum,
                    ]);

                    // One message can only match one lead; stop checking IDs
                    break;
                }
            }
        }

        return $detected;
    }

    // ─── Strategy B ─────────────────────────────────────────────────────────

    /**
     * Search by FROM + SINCE, then verify the result is a genuine reply
     * (thread header or Re: subject) and not from an automated sender.
     *
     * @param resource $imap
     */
    private function detectBySenderAddress($imap, Collection $leads): int
    {
        $detected = 0;

        foreach ($leads as $lead) {
            $firstLog = $lead->sendLogs->first();
            if (! $firstLog?->sent_at) {
                continue;
            }

            $since    = $firstLog->sent_at->format('d-M-Y');
            $criteria = sprintf('FROM "%s" SINCE "%s"', $lead->email, $since);
            $results  = @imap_search($imap, $criteria);

            if (! $results) {
                continue;
            }

            foreach ($results as $msgNum) {
                $rawHeaders = @imap_fetchheader($imap, $msgNum);
                if (! $rawHeaders) {
                    continue;
                }

                // Hard-exclude automated / system messages
                if ($this->isAutomatedSender($rawHeaders)) {
                    continue;
                }

                // Require at least one positive signal: reply header OR Re: subject.
                // Without this gate, an auto-responder FROM the same address would
                // trigger a false positive, even after the automated-sender check.
                if ($this->hasReplyHeader($rawHeaders) || $this->hasReplySubject($rawHeaders)) {
                    $lead->markReplied();
                    $detected++;

                    $this->audit->replyDetected($lead->id, 'sender_address');

                    $this->logger->info('[Outreach] Reply detected via sender address', [
                        'lead_id' => $lead->id,
                        'email'   => $lead->email,
                        'msg_num' => $msgNum,
                    ]);

                    // One confirmed reply is enough — stop scanning other messages
                    break;
                }
            }
        }

        return $detected;
    }

    // ─── Header Helpers ─────────────────────────────────────────────────────

    /**
     * Extract the value of a named header from a raw RFC 2822 header block.
     * Handles folded headers (continuation lines starting with whitespace).
     * Returns an empty string if the header is absent.
     */
    private function extractHeader(string $rawHeaders, string $name): string
    {
        // Match "Header-Name:" followed by value, including folded continuations
        $pattern = '/^' . preg_quote($name, '/') . ':\s*(.+(?:\r?\n[ \t].+)*)/im';
        if (preg_match($pattern, $rawHeaders, $m)) {
            // Collapse folding whitespace
            return preg_replace('/\r?\n[ \t]+/', ' ', trim($m[1]));
        }
        return '';
    }

    /**
     * Return true if $headerValue contains $messageId as a complete token.
     * A Message-ID token is bounded by angle brackets, spaces, or string edges.
     */
    private function headerContainsMessageId(string $headerValue, string $messageId): bool
    {
        if ($headerValue === '' || $messageId === '') {
            return false;
        }
        // Normalize: strip surrounding angle brackets for comparison
        $needle = trim($messageId, '<>');
        // Match the bare ID or the <id> form
        return str_contains($headerValue, $needle);
    }

    /**
     * Return true if the raw headers contain In-Reply-To or References
     * with a non-empty value, indicating this is a reply in a thread.
     */
    private function hasReplyHeader(string $rawHeaders): bool
    {
        $inReplyTo  = $this->extractHeader($rawHeaders, 'In-Reply-To');
        $references = $this->extractHeader($rawHeaders, 'References');
        return $inReplyTo !== '' || $references !== '';
    }

    /**
     * Return true if the Subject header starts with a recognized reply prefix.
     */
    private function hasReplySubject(string $rawHeaders): bool
    {
        $subject = strtolower(trim($this->extractHeader($rawHeaders, 'Subject')));
        foreach (self::REPLY_SUBJECT_PREFIXES as $prefix) {
            if (str_starts_with($subject, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return true if the From header matches any known automated-sender pattern.
     * Checks both From name and From address (case-insensitive substring match).
     */
    private function isAutomatedSender(string $rawHeaders): bool
    {
        $from = strtolower($this->extractHeader($rawHeaders, 'From'));
        foreach (self::AUTOMATED_SENDER_PATTERNS as $pattern) {
            if (str_contains($from, $pattern)) {
                return true;
            }
        }
        return false;
    }
}
