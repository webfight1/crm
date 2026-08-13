<?php

namespace App\Mail;

use App\Models\MeritReminderSetting;
use App\Models\Setting;
use App\Services\Merit\MeritDebtor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Meriti võlgnikule saadetav astmeline (1..3) meeldetuletus.
 * Teema ja sisu tulevad seadete mallidest, kohatäited asendatakse.
 */
class OverdueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{name: string, content: string}>  $pdfAttachments  Arvete PDF-id (base64).
     */
    public function __construct(
        public MeritDebtor $debtor,
        public int $level,
        public MeritReminderSetting $settings,
        public array $pdfAttachments = [],
        public bool $isTest = false,
    ) {
    }

    public function envelope(): Envelope
    {
        $step = $this->settings->step($this->level);
        $subject = $this->applyPlaceholders($step['subject'] ?: MeritReminderSetting::defaultBody($this->level));
        // Teema ei tohi olla mitmerealine — võta esimene rida.
        $subject = trim(strtok($subject, "\n")) ?: 'Meeldetuletus tasumata arve kohta';

        if ($this->isTest) {
            $subject = '[TEST → ' . ($this->debtor->email ?: '?') . '] ' . $subject;
        }

        $from = null;
        if ($this->settings->from_email) {
            $from = new \Illuminate\Mail\Mailables\Address(
                $this->settings->from_email,
                $this->settings->from_name ?: config('app.name')
            );
        }

        return new Envelope(
            subject: $subject,
            from: $from,
        );
    }

    public function content(): Content
    {
        $step = $this->settings->step($this->level);
        $body = $this->applyPlaceholders($step['body'] ?: MeritReminderSetting::defaultBody($this->level));

        return new Content(
            view: 'emails.merit-reminder',
            with: [
                'bodyText' => $body,
                'debtor'   => $this->debtor,
            ],
        );
    }

    /** Arvete PDF-id manustena. */
    public function attachments(): array
    {
        return collect($this->pdfAttachments)
            ->filter(fn ($a) => is_array($a) && ! empty($a['content']))
            ->map(fn (array $a) => Attachment::fromData(
                fn () => base64_decode($a['content']),
                $a['name'] ?? 'arve.pdf'
            )->withMime('application/pdf'))
            ->values()
            ->all();
    }

    /** Asenda kohatäited võlgniku andmetega. */
    private function applyPlaceholders(string $template): string
    {
        $company = $this->settings->from_name
            ?: (Setting::getSettings()->company_name ?? config('app.name'));

        return strtr($template, [
            '{{nimi}}'     => $this->debtor->greetingName(),
            '{{arved}}'    => $this->debtor->invoiceListText(),
            '{{summa}}'    => $this->debtor->formattedTotal(),
            '{{paevad}}'   => (string) $this->debtor->maxOverdueDays,
            '{{ettevote}}' => (string) $company,
        ]);
    }
}
