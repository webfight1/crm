<?php

namespace App\Services\Merit;

use Carbon\Carbon;

/**
 * Üks Meriti võlgnik koos tema üle tähtaja arvete kokkuvõttega.
 * Lihtne väärtusobjekt, mida service koostab ja vaated/mailer tarbivad.
 */
class MeritDebtor
{
    /**
     * @param  array<int, array{doc_no: string, due_date: ?Carbon, unpaid: float, currency: string}>  $invoices
     */
    public function __construct(
        public string $customerId,
        public string $name,
        public ?string $contact,
        public ?string $email,
        public array $invoices,
        public float $totalUnpaid,
        public int $maxOverdueDays,
        public string $currency = 'EUR',
        public ?string $emailSource = null, // 'merit' | 'manual' | null
    ) {
    }

    /** Nimi pöördumiseks: eelista kontaktisikut, muidu ettevõtte nimi. */
    public function greetingName(): string
    {
        $name = trim((string) ($this->contact ?: $this->name));

        return $name !== '' ? $name : 'klient';
    }

    public function hasEmail(): bool
    {
        return is_string($this->email) && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function formattedTotal(): string
    {
        return number_format($this->totalUnpaid, 2, ',', ' ') . ' ' . $this->currency;
    }

    /** Arvete nimekiri tekstina (e-kirja {{arved}} kohatäite jaoks). */
    public function invoiceListText(): string
    {
        return collect($this->invoices)->map(function (array $inv): string {
            $due = $inv['due_date'] ? $inv['due_date']->format('d.m.Y') : '-';
            $amount = number_format($inv['unpaid'], 2, ',', ' ') . ' ' . $inv['currency'];

            return "  • Arve {$inv['doc_no']} — tähtaeg {$due} — tasumata {$amount}";
        })->implode("\n");
    }
}
