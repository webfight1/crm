<?php

namespace App\Mail;

use App\Models\Quotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class QuotationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected Quotation $quotation,
        protected string $pdfPath
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Pakkumine') . ' #' . $this->quotation->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quotation',
            with: [
                'quotation' => $this->quotation,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as("pakkumine_{$this->quotation->number}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
