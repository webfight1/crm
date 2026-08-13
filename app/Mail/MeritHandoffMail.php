<?php

namespace App\Mail;

use App\Services\Merit\MeritDebtor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sisemine teavitus (nt marius@kind.ee-le): kliendile on saadetud maksimaalne
 * arv meeldetuletusi, automaatika peatub — palun helista kliendile.
 */
class MeritHandoffMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MeritDebtor $debtor,
        public int $count,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Helista võlgnikule: ' . ($this->debtor->name ?: 'klient')
                . ' (' . $this->count . ' meeldetuletust saadetud)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.merit-handoff',
            with: [
                'debtor' => $this->debtor,
                'count'  => $this->count,
            ],
        );
    }
}
