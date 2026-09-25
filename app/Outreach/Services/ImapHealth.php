<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachEmailAccount;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;

/**
 * Keeps transient IMAP hiccups out of the error alerts.
 *
 * c-client queues every error/alert internally. Unless imap_errors() and
 * imap_alerts() drain that queue, PHP dumps it as a "PHP Request Shutdown"
 * notice when the worker exits, which is logged as an error and turns into a
 * Telegram alert with no mailbox name. A single dropped connection
 * ("[CLOSED] IMAP connection broken") is routine: the next 5-minute run
 * retries and the UID cursor catches up.
 *
 * So: drain after every session, log one-off failures as warnings, and
 * escalate to an error (→ Telegram, throttled hourly by message) only after
 * FAIL_THRESHOLD consecutive failed runs on the same mailbox.
 */
class ImapHealth
{
    public const FAIL_THRESHOLD = 3;

    /** c-client chatter that is not a failure. */
    private const BENIGN = '/SECURITY PROBLEM: insecure server advertised|Mailbox is empty/i';

    /** Empty c-client's error + alert stacks; returns what was in them. */
    public static function drain(): array
    {
        return array_values(array_unique(array_merge(
            imap_errors() ?: [],
            imap_alerts() ?: [],
        )));
    }

    /** imap_close() + drain, for finally blocks. */
    public static function close($imap): array
    {
        @imap_close($imap);

        return self::drain();
    }

    /**
     * Record the outcome of one run against a mailbox. $errors empty = healthy.
     *
     * @param string[] $errors
     */
    public static function record(OutreachEmailAccount $account, array $errors, LoggerInterface $logger, string $context): void
    {
        $key = "imap-fail:{$account->id}";
        $errors = array_values(array_filter($errors, fn ($e) => ! preg_match(self::BENIGN, (string) $e)));

        if (! $errors) {
            Cache::forget($key);
            return;
        }

        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addDay());

        $details = [
            'account'  => $account->email,
            'context'  => $context,
            'failures' => $count,
            'error'    => implode('; ', $errors),
        ];

        if ($count >= self::FAIL_THRESHOLD) {
            // Stable message text: the Telegram listener throttles per message.
            $logger->error("[Outreach] IMAP postkast {$account->email} ei tööta ("
                . self::FAIL_THRESHOLD . '+ ebaõnnestunud katset järjest)', $details);
        } else {
            $logger->warning('[Outreach] IMAP transient failure', $details);
        }
    }
}
