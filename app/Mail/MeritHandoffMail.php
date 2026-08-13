<?php

namespace App\Mail;

use App\Services\Merit\MeritInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sisemine teavitus Mariusele: arve on N päeva üle tähtaja ja meeldetuletusi
 * on saadetud — palun võta kliendiga ise ühendust (helista).
 */
class MeritHandoffMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MeritInvoice $invoice,
        public int $step,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Võta ühendust: ' . ($this->invoice->customerName ?: 'klient')
                . ' — arve nr ' . $this->invoice->invoiceNo
                . ' (' . $this->invoice->daysOverdue . ' p üle tähtaja)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.merit-handoff',
            with: ['invoice' => $this->invoice, 'step' => $this->step],
        );
    }
}
