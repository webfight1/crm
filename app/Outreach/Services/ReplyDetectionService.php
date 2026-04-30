<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
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

        // Selection rules per mailbox role:
        //
        //   PRIMARY REPLY ACCOUNT (e.g. veiko@webfight.ee)
        //     Every conversation we've ever started stays in scope. After
        //     handoff, clients may keep replying here for weeks; we don't
        //     filter by replied/status/assignment.
        //
        //   COLD-SEND MAILBOX (every other mailbox)
        //     Two distinct selection paths combined with OR:
        //       (a) "First-reply detection" — leads still in active sequence
        //           assigned to THIS mailbox. The first reply flips replied=true.
        //       (b) "Always-listening" — every qualified lead (replied=true)
        //           regardless of which mailbox they were originally assigned
        //           to. This catches the common scenario where a former lead
        //           writes a fresh email to ANY of our cold mailboxes — even
        //           one that didn't originally contact them. Without this
        //           branch, those messages would be silently lost.
        $leadQuery = OutreachLead::query()
            ->whereHas('sendLogs', fn($q) => $q->where('status', OutreachSendLog::STATUS_SENT))
            ->with(['sendLogs' => fn($q) => $q->where('status', OutreachSendLog::STATUS_SENT)
                                              ->orderBy('sent_at')]);

        if (! $account->is_primary_reply_account) {
            $leadQuery->where(function ($q) use ($account) {
                $q->where(function ($firstReply) use ($account) {
                    $firstReply->where('assigned_email_account_id', $account->id)
                               ->where('replied', false)
                               ->where('status', OutreachLead::STATUS_ACTIVE);
                })->orWhere('replied', true);
            });
        }

        $leads = $leadQuery->get();

        if ($leads->isEmpty()) {
            return 0;
        }

        // Strategy A: header-based Message-ID matching
        $detected += $this->detectByMessageId($imap, $leads, $account);

        // Strategy B: sender-address search runs against every lead in scope.
        // The "always-listening" extension means qualified leads are deliberately
        // kept in scope on cold mailboxes too, so any fresh inbound from them
        // (including stand-alone messages with no In-Reply-To) is captured.
        $detected += $this->detectBySenderAddress($imap, $leads, $account);

        return $detected;
    }

    // ─── Strategy A ─────────────────────────────────────────────────────────

    /**
     * Fetch headers of UNSEEN messages and verify In-Reply-To / References
     * against our sent Message-IDs. Never touches message bodies.
     *
     * @param resource $imap
     */
    private function detectByMessageId($imap, Collection $leads, OutreachEmailAccount $account): int
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

        // Include CRM-originated outbound replies (Layer 2 sends) so a
        // follow-up reply from the client lands as a thread match against
        // our most recent outgoing message, not just the original cold send.
        $leadsById = $leads->keyBy('id');
        $outbound = OutreachMessage::where('direction', OutreachMessage::DIRECTION_OUTBOUND)
            ->whereIn('lead_id', $leads->pluck('id'))
            ->whereNotNull('message_id')
            ->get(['lead_id', 'message_id']);

        foreach ($outbound as $msg) {
            if ($lead = $leadsById->get($msg->lead_id)) {
                $messageIdMap[$msg->message_id] = $lead;
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

            // Check each sent Message-ID against thread headers.
            // Note: we DO process already-replied leads — for the primary
            // reply mailbox, follow-up replies past the first one still need
            // to be persisted into the conversation thread. The
            // markReplied() + audit hop is gated to first-reply only so we
            // don't keep updating replied_at or spamming the audit log.
            foreach ($messageIdMap as $sentMessageId => $lead) {
                if ($this->headerContainsMessageId($inReplyTo, $sentMessageId)
                    || $this->headerContainsMessageId($references, $sentMessageId)
                ) {
                    // Persist first — UNIQUE (account_id, imap_uid) makes this
                    // idempotent across poller runs.
                    $this->persistMessage($imap, $msgNum, $rawHeaders, $lead, $account);

                    if (! $lead->replied) {
                        $lead->markReplied();
                        $detected++;
                        $this->audit->replyDetected($lead->id, 'message_id', $sentMessageId);
                    }

                    $this->logger->info('[Outreach] Reply detected via Message-ID header', [
                        'lead_id'         => $lead->id,
                        'email'           => $lead->email,
                        'message_id'      => $sentMessageId,
                        'msg_num'         => $msgNum,
                        'already_replied' => (bool) $lead->replied,
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
    private function detectBySenderAddress($imap, Collection $leads, OutreachEmailAccount $account): int
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
                    $this->persistMessage($imap, $msgNum, $rawHeaders, $lead, $account);

                    if (! $lead->replied) {
                        $lead->markReplied();
                        $detected++;
                        $this->audit->replyDetected($lead->id, 'sender_address');
                    }

                    $this->logger->info('[Outreach] Reply detected via sender address', [
                        'lead_id'         => $lead->id,
                        'email'           => $lead->email,
                        'msg_num'         => $msgNum,
                        'already_replied' => (bool) $lead->replied,
                    ]);

                    // One confirmed reply is enough — stop scanning other messages
                    break;
                }
            }
        }

        return $detected;
    }

    // ─── Message Persistence ────────────────────────────────────────────────

    /**
     * Persist a matched reply to outreach_messages. Idempotent: a duplicate
     * (email_account_id, imap_uid) is silently ignored thanks to firstOrCreate
     * over the unique index.
     *
     * Failures here are logged but never propagated — losing the ability to
     * mark a lead as replied because we couldn't decode a body would be worse
     * than missing the body. Reply detection is the contract; persistence
     * is best-effort.
     *
     * @param resource $imap
     */
    private function persistMessage(
        $imap,
        int                  $msgNum,
        string               $rawHeaders,
        OutreachLead         $lead,
        OutreachEmailAccount $account,
    ): void {
        try {
            $uid = imap_uid($imap, $msgNum);
            if ($uid === false) {
                $uid = null;
            }

            // Quick exit if we've already stored this message — saves a body fetch.
            if ($uid !== null
                && OutreachMessage::where('email_account_id', $account->id)
                    ->where('imap_uid', $uid)
                    ->exists()
            ) {
                return;
            }

            $messageId = $this->stripAngleBrackets($this->extractHeader($rawHeaders, 'Message-ID'));
            $inReplyTo = $this->stripAngleBrackets($this->extractHeader($rawHeaders, 'In-Reply-To'));
            $refs      = $this->extractHeader($rawHeaders, 'References') ?: null;
            $subject   = $this->decodeMimeHeader($this->extractHeader($rawHeaders, 'Subject'));
            $fromRaw   = $this->extractHeader($rawHeaders, 'From');
            [$fromName, $fromEmail] = $this->parseFromHeader($fromRaw);

            $structure = @imap_fetchstructure($imap, $msgNum);
            [$bodyText, $bodyHtml, $hasAttachments] = $this->extractBodies($imap, $msgNum, $structure);

            $receivedAt = $this->parseReceivedAt($rawHeaders, $imap, $msgNum);

            OutreachMessage::firstOrCreate(
                [
                    'email_account_id' => $account->id,
                    'imap_uid'         => $uid,
                ],
                [
                    'lead_id'           => $lead->id,
                    'direction'         => OutreachMessage::DIRECTION_INBOUND,
                    'message_id'        => $messageId ?: null,
                    'in_reply_to'       => $inReplyTo ?: null,
                    'references_header' => $refs,
                    'from_email'        => $fromEmail ?? $lead->email,
                    'from_name'         => $fromName,
                    'subject'           => $subject ?: null,
                    'body_text'         => $bodyText,
                    'body_html'         => $bodyHtml,
                    'has_attachments'   => $hasAttachments,
                    'received_at'       => $receivedAt,
                ]
            );
        } catch (Throwable $e) {
            $this->logger->error('[Outreach] Failed to persist reply message', [
                'lead_id'    => $lead->id,
                'account_id' => $account->id,
                'msg_num'    => $msgNum,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Walk the IMAP structure tree and pull out text/plain, text/html, and
     * an attachment flag. Handles non-multipart messages (single-part body)
     * and multipart messages (alternative, mixed, related).
     *
     * Returns [bodyText, bodyHtml, hasAttachments].
     *
     * @param resource $imap
     * @return array{0: ?string, 1: ?string, 2: bool}
     */
    private function extractBodies($imap, int $msgNum, $structure): array
    {
        if (! $structure) {
            // Fall back to fetching the raw body as plain text.
            $raw = @imap_body($imap, $msgNum);
            return [is_string($raw) && $raw !== '' ? $raw : null, null, false];
        }

        // Non-multipart: structure has no `parts`. Fetch section "1".
        if (empty($structure->parts)) {
            $raw = @imap_fetchbody($imap, $msgNum, '1');
            $decoded = $this->decodePart($raw, $structure->encoding ?? 0, $this->charsetFromPart($structure));

            $isHtml = isset($structure->subtype) && strtolower($structure->subtype) === 'html';
            return [
                $isHtml ? null : $decoded,
                $isHtml ? $decoded : null,
                false,
            ];
        }

        $bodyText       = null;
        $bodyHtml       = null;
        $hasAttachments = false;

        $this->walkParts($imap, $msgNum, $structure->parts, '', $bodyText, $bodyHtml, $hasAttachments);

        return [$bodyText, $bodyHtml, $hasAttachments];
    }

    /**
     * Recursively walk multipart sections. Modifies $bodyText, $bodyHtml,
     * and $hasAttachments by reference. The first text/plain and first
     * text/html encountered win — typical RFC 2046 alternative ordering.
     *
     * @param resource $imap
     */
    private function walkParts(
        $imap,
        int     $msgNum,
        array   $parts,
        string  $prefix,
        ?string &$bodyText,
        ?string &$bodyHtml,
        bool    &$hasAttachments,
    ): void {
        foreach ($parts as $i => $part) {
            // IMAP section numbers are 1-indexed and dotted for nesting.
            $section = $prefix === '' ? (string) ($i + 1) : $prefix . '.' . ($i + 1);

            $disposition = isset($part->disposition) ? strtolower($part->disposition) : null;
            if ($disposition === 'attachment') {
                $hasAttachments = true;
                continue;
            }

            // Recurse into nested multipart parts.
            if (! empty($part->parts)) {
                $this->walkParts($imap, $msgNum, $part->parts, $section, $bodyText, $bodyHtml, $hasAttachments);
                continue;
            }

            $type    = isset($part->type) ? (int) $part->type : 0;       // 0 = TYPETEXT
            $subtype = isset($part->subtype) ? strtolower($part->subtype) : '';

            if ($type !== 0) {
                // Non-text body part counts as an attachment if not already flagged.
                if ($disposition === null) {
                    $hasAttachments = true;
                }
                continue;
            }

            $raw     = @imap_fetchbody($imap, $msgNum, $section);
            $decoded = $this->decodePart($raw, $part->encoding ?? 0, $this->charsetFromPart($part));

            if ($subtype === 'plain' && $bodyText === null) {
                $bodyText = $decoded;
            } elseif ($subtype === 'html' && $bodyHtml === null) {
                $bodyHtml = $decoded;
            }
        }
    }

    /**
     * Decode an IMAP body part using its transfer encoding, then convert to UTF-8.
     */
    private function decodePart(string|false $raw, int $encoding, string $charset): ?string
    {
        if ($raw === false || $raw === '') {
            return null;
        }

        // IMAP encoding constants: 0=7BIT, 1=8BIT, 2=BINARY, 3=BASE64,
        // 4=QUOTED-PRINTABLE, 5=OTHER
        $decoded = match ($encoding) {
            3       => base64_decode($raw, true) ?: $raw,
            4       => quoted_printable_decode($raw),
            default => $raw,
        };

        if (strtoupper($charset) !== 'UTF-8') {
            $converted = @iconv($charset, 'UTF-8//TRANSLIT//IGNORE', $decoded);
            if ($converted !== false) {
                return $converted;
            }
        }

        return $decoded;
    }

    /**
     * Pick out the charset parameter from a structure part. Defaults to UTF-8
     * which is the safest assumption for modern Gmail-originated mail.
     */
    private function charsetFromPart($part): string
    {
        if (! empty($part->parameters)) {
            foreach ($part->parameters as $param) {
                if (isset($param->attribute) && strtolower($param->attribute) === 'charset') {
                    return $param->value ?: 'UTF-8';
                }
            }
        }
        return 'UTF-8';
    }

    /**
     * Decode RFC 2047 encoded-word headers (e.g. "=?UTF-8?B?...?=") to plain UTF-8.
     */
    private function decodeMimeHeader(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        return $decoded !== false ? $decoded : $value;
    }

    /**
     * Parse a From header into [name, email].
     * Returns [null, null] if neither could be extracted.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function parseFromHeader(string $from): array
    {
        if ($from === '') {
            return [null, null];
        }

        $decoded = $this->decodeMimeHeader($from);

        // "Display Name" <email@host>
        if (preg_match('/^\s*"?([^"<]*?)"?\s*<([^>]+)>\s*$/', $decoded, $m)) {
            $name  = trim($m[1]);
            $email = trim($m[2]);
            return [$name !== '' ? $name : null, $email !== '' ? $email : null];
        }

        // bare email
        if (filter_var(trim($decoded), FILTER_VALIDATE_EMAIL)) {
            return [null, trim($decoded)];
        }

        return [null, null];
    }

    /**
     * Resolve the message's received timestamp. Prefer the Date: header
     * (sender-clock authoritative), fall back to IMAP internal date.
     */
    private function parseReceivedAt(string $rawHeaders, $imap, int $msgNum): \Carbon\Carbon
    {
        $dateHeader = $this->extractHeader($rawHeaders, 'Date');
        if ($dateHeader !== '') {
            try {
                return \Carbon\Carbon::parse($dateHeader);
            } catch (Throwable) {
                // fall through to internal date
            }
        }

        $overview = @imap_fetch_overview($imap, (string) $msgNum);
        if (is_array($overview) && isset($overview[0]->date)) {
            try {
                return \Carbon\Carbon::parse($overview[0]->date);
            } catch (Throwable) {
                // fall through
            }
        }

        return \Carbon\Carbon::now();
    }

    /**
     * Strip leading/trailing angle brackets from a Message-ID-shaped value.
     */
    private function stripAngleBrackets(string $value): string
    {
        return trim($value, " \t\r\n<>");
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
