<?php

namespace App\Services\Merit;

use Carbon\Carbon;

/**
 * Üks üle tähtaja arve (meeldetuletuste ühik — iga arve eraldi arvestus).
 */
class MeritInvoice
{
    public function __construct(
        public string $customerId,
        public string $customerName,
        public ?string $contact,
        public ?string $email,
        public string $invoiceNo,
        public ?Carbon $dueDate,
        public float $unpaid,
        public int $daysOverdue,
        public string $currency = 'EUR',
        public ?string $emailSource = null, // 'merit' | 'manual' | null
    ) {
    }

    /** Unikaalne võti arve kohta (klient + arve number). */
    public function key(): string
    {
        return $this->customerId . '|' . $this->invoiceNo;
    }

    public function greetingName(): string
    {
        $name = trim((string) ($this->contact ?: $this->customerName));

        return $name !== '' ? $name : 'klient';
    }

    public function hasEmail(): bool
    {
        return is_string($this->email) && filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function formattedUnpaid(): string
    {
        return number_format($this->unpaid, 2, ',', ' ') . ' ' . $this->currency;
    }

    public function dueDateFormatted(): string
    {
        return $this->dueDate ? $this->dueDate->format('d.m.Y') : '-';
    }
}
