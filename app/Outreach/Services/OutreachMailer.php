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
     * @return string The SMTP Message-ID assigned to the sent email.
     * @throws \Throwable on transport failure.
     */
    public function send(
        OutreachEmailAccount $account,
        string               $toEmail,
        string               $toName,
        string               $subject,
        string               $htmlBody,
    ): string {
        $messageId = $this->generateMessageId($account->smtp_host ?? 'outreach');

        $email = (new Email())
            ->from(new Address($account->email, $account->name))
            ->to(new Address($toEmail, $toName))
            ->subject($subject)
            ->html($htmlBody)
            ->messageId($messageId);

        $transport      = $this->buildTransport($account);
        $symfonyMailer  = new SymfonyMailer($transport);

        $this->logger->info('[Outreach] Sending email', [
            'from'       => $account->email,
            'to'         => $toEmail,
            'subject'    => $subject,
            'message_id' => $messageId,
        ]);

        $symfonyMailer->send($email);

        return $messageId;
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
