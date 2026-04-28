<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachEmailAccount;
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
    ): string {
        $messageId = $this->generateMessageId($account->smtp_host ?? 'outreach');

        $email = (new Email())
            ->from(new Address($account->email, $account->name))
            ->to(new Address($toEmail, $toName))
            ->subject($subject)
            ->html($htmlBody);

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
}
