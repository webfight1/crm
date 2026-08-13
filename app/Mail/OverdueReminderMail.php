<?php

namespace App\Mail;

use App\Models\MeritReminderSetting;
use App\Services\Merit\MeritInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Ühe arve meeldetuletus (aste 1..4). Teema ja sisu tulevad astme mallist,
 * kohatäited asendatakse arve andmetega.
 */
class OverdueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{name: string, content: string}>  $pdfAttachments
     */
    public function __construct(
        public MeritInvoice $invoice,
        public int $step,
        public MeritReminderSetting $settings,
        public array $pdfAttachments = [],
        public bool $isTest = false,
    ) {
    }

    public function envelope(): Envelope
    {
        $tpl = $this->settings->step($this->step);
        $subject = $this->fill($tpl['subject'] ?: MeritReminderSetting::defaultSubject($this->step));
        $subject = trim(strtok($subject, "\n")) ?: 'Meeldetuletus tasumata arve kohta';

        if ($this->isTest) {
            $subject = '[TEST → ' . ($this->invoice->email ?: '?') . '] ' . $subject;
        }

        $from = null;
        if ($this->settings->from_email) {
            $from = new Address($this->settings->from_email, $this->settings->from_name ?: config('app.name'));
        }

        return new Envelope(subject: $subject, from: $from);
    }

    public function content(): Content
    {
        $tpl = $this->settings->step($this->step);
        $body = $this->fill($tpl['body'] ?: MeritReminderSetting::defaultBody($this->step));

        return new Content(
            view: 'emails.merit-reminder',
            with: ['bodyText' => $body],
        );
    }

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

    /** Asenda kohatäited arve andmetega. */
    private function fill(string $template): string
    {
        return strtr($template, [
            '{{arve_nr}}'  => $this->invoice->invoiceNo,
            '{{ettevote}}' => $this->settings->companyName(),
            '{{nimi}}'     => $this->invoice->greetingName(),
            '{{tahtaeg}}'  => $this->invoice->dueDateFormatted(),
            '{{summa}}'    => $this->invoice->formattedUnpaid(),
            '{{paevad}}'   => (string) $this->invoice->daysOverdue,
        ]);
    }
}
