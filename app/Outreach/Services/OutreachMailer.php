<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachEmailAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * OutreachMailer
 *
 * Isolated sending layer for the outreach module.
 * Builds a fresh Symfony transport per inbox — completely separate
 * from the application's existing mail infrastructure.
 */
class OutreachMailer
{
    public function __construct(private readonly LoggerInterface $logger) {}

    /**
     * Send a single outreach email.
     *
     * $inReplyTo and $references seed RFC 2822 thread headers so Gmail (and
     * other compliant clients) place the message in the same conversation as
     * the prior exchange. This is what makes Layer 2 handoff work: a reply
     * sent from veiko@webfight.ee lands in the same client-side thread as the
     * original cold email from a sacrificial mailbox.
     *
     * @return string The SMTP Message-ID assigned to the sent email.
     * @throws \Throwable on transport failure.
     */
    public function send(
        OutreachEmailAccount $account,
        string               $toEmail,
        string               $toName,
        string               $subject,
        string               $htmlBody,
        ?string              $inReplyTo = null,
        ?string              $references = null,
        array                $attachments = [],
    ): string {
        // Append the account-level HTML signature (if set) before any branch.
        // Both the SMTP path and the Zone Relay path use the same body, so
        // doing it once here guarantees consistency across all send routes
        // and across all callers (campaign sends + manual CRM replies).
        $htmlBody = $this->withSignature($htmlBody, $account);

        // Branch on provider — Zone Relay accounts cannot reach SMTP from a
        // remote VPS, so route through an HMAC-authenticated HTTP endpoint
        // that lives on the host's web server (e.g. webfight.ee/mail-relay.php).
        if ($account->usesRelay()) {
            return $this->sendViaRelay(
                $account,
                $toEmail,
                $toName,
                $subject,
                $htmlBody,
                $inReplyTo,
                $references,
                $attachments,
            );
        }

        $messageId = $this->generateMessageId($account->smtp_host ?? 'outreach');

        $email = (new Email())
            ->from(new Address($account->email, $account->name))
            ->to(new Address($toEmail, $toName))
            ->subject($subject)
            ->html($htmlBody);

        // Attach files. Each attachment is [path, name, mime].
        foreach ($attachments as $a) {
            if (! empty($a['path']) && is_file($a['path'])) {
                $email->attachFromPath(
                    $a['path'],
                    $a['name'] ?? basename($a['path']),
                    $a['mime'] ?? null,
                );
            }
        }

        $headers = $email->getHeaders();
        $headers->addIdHeader('Message-ID', $messageId);

        if ($inReplyTo !== null && $inReplyTo !== '') {
            // Both headers expect angle-bracket-wrapped Message-IDs. Strip any
            // existing brackets so we don't end up with <<...>>.
            $bracketed = $this->bracketMessageId($inReplyTo);
            $headers->addTextHeader('In-Reply-To', $bracketed);
        }

        if ($references !== null && $references !== '') {
            // References is a space-separated chain of message IDs. We accept
            // a pre-built chain (caller assembles the prior thread) and emit
            // it verbatim, only normalizing whitespace.
            $normalized = preg_replace('/\s+/', ' ', trim($references));
            $headers->addTextHeader('References', $normalized);
        }

        $transport      = $this->buildTransport($account);
        $symfonyMailer  = new SymfonyMailer($transport);

        $this->logger->info('[Outreach] Sending email', [
            'from'         => $account->email,
            'to'           => $toEmail,
            'subject'      => $subject,
            'message_id'   => $messageId,
            'in_reply_to'  => $inReplyTo,
        ]);

        $symfonyMailer->send($email);

        return $messageId;
    }

    /**
     * Send a single email through an HMAC-authenticated HTTP relay running
     * on the host that owns the From: domain (e.g. zone.ee shared hosting).
     *
     * The relay receives a JSON payload, verifies the HMAC signature against
     * the shared secret, and dispatches via local PHP mail(). It returns the
     * Message-ID it actually used so we can store it for thread matching.
     *
     * Throws \RuntimeException on any auth or transport failure — caller
     * (OutreachEmailService) treats this the same as an SMTP transport
     * failure, so retry / failure-counter behaviour is identical.
     */
    private function sendViaRelay(
        OutreachEmailAccount $account,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $inReplyTo,
        ?string $references,
    ): string {
        $relayUrl    = trim((string) $account->relay_url);
        $relaySecret = (string) $account->relay_secret;

        if ($relayUrl === '' || $relaySecret === '') {
            throw new \RuntimeException(
                'Zone Relay account is missing relay_url or relay_secret. Configure them on the account.'
            );
        }

        // Generate the Message-ID locally so we can store it before the HTTP
        // round-trip — same contract as the SMTP path.
        $domain = parse_url($relayUrl, PHP_URL_HOST) ?: 'webfight.ee';
        $messageId = $this->generateMessageId($domain);

        $payload = [
            'from_email'  => $account->email,
            'from_name'   => $account->name,
            'to_email'    => $toEmail,
            'to_name'     => $toName,
            'subject'     => $subject,
            'html_body'   => $htmlBody,
            'message_id'  => $messageId,
            'in_reply_to' => $inReplyTo !== null && $inReplyTo !== ''
                                ? $this->bracketMessageId($inReplyTo)
                                : null,
            'references'  => $references !== null && $references !== ''
                                ? preg_replace('/\s+/', ' ', trim($references))
                                : null,
        ];

        $body      = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . $body, $relaySecret);

        $this->logger->info('[Outreach] Sending email via relay', [
            'from'        => $account->email,
            'to'          => $toEmail,
            'subject'     => $subject,
            'message_id'  => $messageId,
            'in_reply_to' => $inReplyTo,
            'relay_url'   => $relayUrl,
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'X-Timestamp'  => $timestamp,
                    'X-Signature'  => $signature,
                    'Content-Type' => 'application/json',
                ])
                ->withBody($body, 'application/json')
                ->post($relayUrl);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Relay HTTP failure: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            throw new \RuntimeException(sprintf(
                'Relay returned %d: %s',
                $response->status(),
                Str::limit((string) $response->body(), 300),
            ));
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['ok'])) {
            throw new \RuntimeException(
                'Relay rejected: ' . Str::limit((string) $response->body(), 300)
            );
        }

        // Honour any Message-ID the relay assigned, falling back to ours.
        return (string) ($json['message_id'] ?? $messageId);
    }

    private function bracketMessageId(string $id): string
    {
        $stripped = trim($id, " \t\r\n<>");
        return '<' . $stripped . '>';
    }

    // ─── Transport Builder ──────────────────────────────────────────────────

    private function buildTransport(OutreachEmailAccount $account): TransportInterface
    {
        $scheme   = $this->resolveScheme($account);
        $password = $account->smtp_password ?? '';   // Already decrypted by model accessor

        $dsn = sprintf(
            '%s://%s:%s@%s:%d',
            $scheme,
            rawurlencode($account->smtp_username ?? ''),
            rawurlencode($password),
            $account->smtp_host,
            $account->smtp_port,
        );

        return Transport::fromDsn($dsn);
    }

    private function resolveScheme(OutreachEmailAccount $account): string
    {
        return match (strtolower((string) $account->smtp_encryption)) {
            'ssl'  => 'smtps',
            'tls'  => 'smtp',
            default => 'smtp',
        };
    }

    private function generateMessageId(string $domain): string
    {
        // Strip non-domain characters just in case
        $domain = preg_replace('/[^a-zA-Z0-9.\-]/', '', $domain) ?: 'outreach';

        return sprintf('%s.%s@%s', now()->format('YmdHis'), Str::random(12), $domain);
    }

    /**
     * Append the account's HTML signature to the message body. Skipped
     * silently when no signature is configured so existing campaign sends
     * stay byte-identical. The double-<br> separator keeps the signature
     * visually distinct from the body without forcing the caller to mind
     * trailing whitespace.
     */
    private function withSignature(string $htmlBody, OutreachEmailAccount $account): string
    {
        $sig = trim((string) ($account->signature_html ?? ''));
        if ($sig === '') {
            return $htmlBody;
        }
        return rtrim($htmlBody) . '<br><br>' . $sig;
    }
}
