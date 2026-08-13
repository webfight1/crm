<?php

namespace App\Services\Merit;

use App\Mail\MeritHandoffMail;
use App\Mail\OverdueReminderMail;
use App\Models\MeritCustomerEmail;
use App\Models\MeritInvoiceState;
use App\Models\MeritReminderLog;
use App\Models\MeritReminderSetting;
use App\Outreach\Models\OutreachEmailAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OverdueReminderService — kogub Meritist üle tähtaja arved ja saadab neile
 * astmelised meeldetuletused. Ühik on ARVE (iga arve eraldi arvestus).
 *
 * Rütm (astme päevakünnised, nt 0/2/9/12): iga arve kohta saadetakse iga aste
 * korra, kui arve on vastavalt palju üle tähtaja. notify_step (nt 3) juures
 * saadetakse Mariusele teavitus. Peale viimast astet automaatika peatub.
 *
 * Kirjad saadetakse Meriti oma saatja (MERIT_MAIL_*, nt arved@kind.ee) või
 * tagavarana aktiivse outreach-postkasti kaudu.
 */
class OverdueReminderService
{
    /** @var array<string, array<string, mixed>|null> */
    private array $customerCache = [];

    private ?string $outreachMailer = null;

    private bool $mailerResolved = false;

    public function __construct(
        private readonly MeritClient $client,
    ) {
    }

    // ─── Saatja ───────────────────────────────────────────────────────────────

    public function resolveSendAccount(): ?OutreachEmailAccount
    {
        return OutreachEmailAccount::query()
            ->where('is_active', true)
            ->whereNotNull('smtp_host')
            ->whereNull('relay_url')
            ->orderByDesc('is_primary_reply_account')
            ->orderBy('id')
            ->first();
    }

    /** Seadistab maileri (Meriti oma saatja või outreach-fallback); tagastab nime või null. */
    public function configureMailer(): ?string
    {
        if ($this->mailerResolved) {
            return $this->outreachMailer;
        }
        $this->mailerResolved = true;

        $m = config('services.merit.mail');
        if (! empty($m['username']) && ! empty($m['password'])) {
            config([
                'mail.mailers.merit_sender' => [
                    'transport'  => 'smtp',
                    'host'       => $m['host'] ?: 'smtp.gmail.com',
                    'port'       => (int) ($m['port'] ?: 587),
                    'encryption' => $m['encryption'] ?: 'tls',
                    'username'   => $m['username'],
                    'password'   => $m['password'],
                ],
                'mail.from.address' => $m['username'],
                'mail.from.name'    => $m['from_name'] ?: config('app.name'),
            ]);

            return $this->outreachMailer = 'merit_sender';
        }

        $account = $this->resolveSendAccount();
        if ($account === null) {
            return $this->outreachMailer = null;
        }

        config([
            'mail.mailers.merit_outreach' => [
                'transport'  => 'smtp',
                'host'       => $account->smtp_host,
                'port'       => (int) $account->smtp_port,
                'encryption' => $account->smtp_encryption,
                'username'   => $account->smtp_username,
                'password'   => $account->smtp_password,
            ],
            'mail.from.address' => $account->email,
            'mail.from.name'    => MeritReminderSetting::getSettings()->from_name
                ?: ($account->name ?: config('app.name')),
        ]);

        return $this->outreachMailer = 'merit_outreach';
    }

    // ─── Andmete kogumine ──────────────────────────────────────────────────────

    /**
     * Üle tähtaja arved eraldi ühikutena (üks kirje arve kohta), rikastatud
     * kliendi kontaktandmete ja käsitsi lisatud e-postidega.
     *
     * @return Collection<int, MeritInvoice>
     */
    public function collectInvoices(): Collection
    {
        $rows = collect($this->client->getOverdueDebts(0))
            ->filter(fn (array $r) => (float) ($r['UnPaidAmount'] ?? 0) > 0 && ($r['DocType'] ?? '') !== 'SO');

        $overrides = MeritCustomerEmail::pluck('email', 'merit_customer_id');
        $today = Carbon::today();

        // Kliendi andmed korra kliendi kohta.
        $custInfo = [];

        return $rows->map(function (array $r) use ($overrides, $today, &$custInfo): MeritInvoice {
            $customerId = (string) ($r['PartnerId'] ?? $r['PartnerName'] ?? '');
            $partnerName = (string) ($r['PartnerName'] ?? '');

            if (! array_key_exists($customerId, $custInfo)) {
                $custInfo[$customerId] = $customerId !== '' ? $this->lookupCustomer($customerId) : null;
            }
            $customer = $custInfo[$customerId];

            $meritEmail = $customer['Email'] ?? null;
            $meritEmail = is_string($meritEmail) && trim($meritEmail) !== '' ? trim($meritEmail) : null;
            $email = $meritEmail;
            $source = $meritEmail !== null ? 'merit' : null;
            if ($email === null && isset($overrides[$customerId]) && $overrides[$customerId] !== '') {
                $email = $overrides[$customerId];
                $source = 'manual';
            }

            $due = $this->parseMeritDate($r['DueDate'] ?? null);
            $daysOverdue = $due ? max(0, (int) $due->diffInDays($today, false)) : 0;

            return new MeritInvoice(
                customerId: $customerId,
                customerName: $customer['Name'] ?? $partnerName,
                contact: $customer['Contact'] ?? null,
                email: $email,
                invoiceNo: (string) ($r['DocNo'] ?? '—'),
                dueDate: $due,
                unpaid: round((float) ($r['UnPaidAmount'] ?? 0), 2),
                daysOverdue: $daysOverdue,
                currency: (string) ($r['CurrencyCode'] ?? 'EUR') ?: 'EUR',
                emailSource: $source,
            );
        })->values();
    }

    /**
     * Võlgnikud kliendi kaupa (e-postide halduse lehe jaoks).
     *
     * @return Collection<int, MeritDebtor>
     */
    public function collectDebtors(): Collection
    {
        $overrides = MeritCustomerEmail::pluck('email', 'merit_customer_id');

        return collect($this->client->getOverdueDebts(0))
            ->filter(fn (array $r) => (float) ($r['UnPaidAmount'] ?? 0) > 0 && ($r['DocType'] ?? '') !== 'SO')
            ->groupBy(fn (array $r) => (string) ($r['PartnerId'] ?? $r['PartnerName'] ?? ''))
            ->map(function (Collection $rows, string $customerId) use ($overrides): MeritDebtor {
                $today = Carbon::today();
                $partnerName = (string) ($rows->first()['PartnerName'] ?? '');

                $invoices = [];
                $total = 0.0;
                $maxOverdue = 0;
                $currency = 'EUR';

                foreach ($rows as $r) {
                    $unpaid = (float) ($r['UnPaidAmount'] ?? 0);
                    $due = $this->parseMeritDate($r['DueDate'] ?? null);
                    $currency = (string) ($r['CurrencyCode'] ?? $currency) ?: 'EUR';
                    $maxOverdue = max($maxOverdue, $due ? max(0, (int) $due->diffInDays($today, false)) : 0);
                    $total += $unpaid;
                    $invoices[] = ['doc_no' => (string) ($r['DocNo'] ?? '—'), 'due_date' => $due, 'unpaid' => $unpaid, 'currency' => $currency];
                }

                $customer = $customerId !== '' ? $this->lookupCustomer($customerId) : null;
                $meritEmail = $customer['Email'] ?? null;
                $meritEmail = is_string($meritEmail) && trim($meritEmail) !== '' ? trim($meritEmail) : null;
                $email = $meritEmail;
                $source = $meritEmail !== null ? 'merit' : null;
                if ($email === null && isset($overrides[$customerId]) && $overrides[$customerId] !== '') {
                    $email = $overrides[$customerId];
                    $source = 'manual';
                }

                return new MeritDebtor(
                    customerId: $customerId,
                    name: $customer['Name'] ?? $partnerName,
                    contact: $customer['Contact'] ?? null,
                    email: $email,
                    invoices: $invoices,
                    totalUnpaid: round($total, 2),
                    maxOverdueDays: $maxOverdue,
                    currency: $currency,
                    emailSource: $source,
                );
            })->values();
    }

    // ─── Saatmine ───────────────────────────────────────────────────────────────

    /**
     * Saada meeldetuletused (per-arve). Dry-run korral ainult arvutab.
     *
     * @return array{sent:int, skipped:int, failed:int, notified:int, resolved:int, planned:array<int,array<string,mixed>>}
     */
    public function sendReminders(bool $dryRun = false): array
    {
        $settings = MeritReminderSetting::getSettings();

        $testRecipient = filter_var((string) $settings->test_recipient, FILTER_VALIDATE_EMAIL) ?: null;
        $testMode = $testRecipient !== null;

        if (! $dryRun && $this->configureMailer() === null) {
            throw new \RuntimeException(
                'Saatmiseks pole saatjat seadistatud. Lisa MERIT_MAIL_* (nt arved@kind.ee) .env-i või aktiveeri outreach-postkast.'
            );
        }

        $invoices = $this->collectInvoices();
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'notified' => 0, 'resolved' => 0, 'planned' => []];
        $notifyStep = (int) $settings->notify_step;
        $attachFrom = (int) $settings->attach_from_step;
        $seenKeys = [];

        foreach ($invoices as $inv) {
            $seenKeys[] = $inv->key();

            $state = $testMode
                ? new MeritInvoiceState(['invoice_key' => $inv->key()])
                : MeritInvoiceState::firstOrNew(['invoice_key' => $inv->key()]);

            $step = $this->determineStep($inv, $settings, $state);
            if ($step === null) {
                $result['skipped']++;
                continue;
            }

            $plan = [
                'name'    => $inv->customerName,
                'invoice' => $inv->invoiceNo,
                'email'   => $inv->email,
                'step'    => $step,
                'days'    => $inv->daysOverdue,
                'total'   => $inv->formattedUnpaid(),
            ];

            if (! $inv->hasEmail()) {
                $plan['result'] = 'no_email';
                $result['planned'][] = $plan;
                $result['skipped']++;
                if (! $dryRun) {
                    $this->log($inv, $step, 'skipped', 'E-posti aadress puudub');
                }
                continue;
            }

            if ($dryRun) {
                $plan['result'] = 'would_send';
                $result['planned'][] = $plan;
                $result['sent']++;
                continue;
            }

            try {
                $attachments = $step >= $attachFrom ? $this->buildInvoiceAttachment($inv) : [];

                Mail::mailer($this->outreachMailer)
                    ->to($testMode ? $testRecipient : $inv->email)
                    ->send(new OverdueReminderMail($inv, $step, $settings, $attachments, $testMode));

                // Teavitus Mariusele, kui jõuti notify_step astmeni või sellest edasi
                // (ka kui vana arve hüppab kohe kõrgemale astmele) — üks kord arve kohta.
                $notify = $step >= $notifyStep && ($testMode || $state->marius_notified_at === null);
                if ($notify) {
                    $this->sendHandoff($inv, $step, $testMode ? $testRecipient : $settings->handoffRecipient());
                    $result['notified']++;
                }

                if (! $testMode) {
                    $this->log($inv, $step, 'sent');
                    $state->fill([
                        'merit_customer_id'  => $inv->customerId,
                        'invoice_no'         => $inv->invoiceNo,
                        'highest_step_sent'  => $step,
                        'last_sent_at'       => now(),
                        'resolved_at'        => null,
                        'marius_notified_at' => $notify ? now() : $state->marius_notified_at,
                    ])->save();
                }

                $plan['result'] = $testMode ? 'test_sent' : 'sent';
                $result['planned'][] = $plan;
                $result['sent']++;
            } catch (\Throwable $e) {
                Log::error('[Merit] Meeldetuletuse saatmine ebaõnnestus', [
                    'invoice' => $inv->invoiceNo,
                    'error'   => $e->getMessage(),
                ]);
                $this->log($inv, $step, 'failed', $e->getMessage());
                $plan['result'] = 'failed';
                $result['planned'][] = $plan;
                $result['failed']++;
            }
        }

        // Tasutud arved (kadunud võlaraportist) → märgi lahenduks.
        if (! $dryRun && ! $testMode) {
            $result['resolved'] = $this->resetResolvedInvoices($seenKeys);
        }

        return $result;
    }

    /**
     * Eelvaade UI jaoks: iga arve + arvutatud järgmine aste + olek.
     *
     * @return Collection<int, array{invoice: MeritInvoice, step: ?int, state: MeritInvoiceState}>
     */
    public function previewPlan(?MeritReminderSetting $settings = null): Collection
    {
        $settings ??= MeritReminderSetting::getSettings();

        return $this->collectInvoices()->map(function (MeritInvoice $inv) use ($settings) {
            $state = MeritInvoiceState::firstOrNew(['invoice_key' => $inv->key()]);

            return ['invoice' => $inv, 'step' => $this->determineStep($inv, $settings, $state), 'state' => $state];
        });
    }

    /**
     * Mitmes aste on nüüd võlgu (1..STEP_COUNT), või null.
     * Aste = kõrgeim, mille päevakünnis on täidetud ja mida pole veel saadetud.
     */
    public function determineStep(MeritInvoice $invoice, MeritReminderSetting $settings, MeritInvoiceState $state): ?int
    {
        $sent = (int) $state->highest_step_sent;
        if ($sent >= MeritReminderSetting::STEP_COUNT) {
            return null; // kõik astmed tehtud
        }

        $dueStep = 0;
        foreach (range(1, MeritReminderSetting::STEP_COUNT) as $level) {
            if ($invoice->daysOverdue >= (int) $settings->{"step{$level}_days"}) {
                $dueStep = max($dueStep, $level);
            }
        }

        return $dueStep > $sent ? $dueStep : null;
    }

    private function resetResolvedInvoices(array $seenKeys): int
    {
        $query = MeritInvoiceState::where('highest_step_sent', '>', 0)->whereNull('resolved_at');
        if (! empty($seenKeys)) {
            $query->whereNotIn('invoice_key', $seenKeys);
        }

        return $query->update([
            'highest_step_sent'  => 0,
            'resolved_at'        => now(),
            'marius_notified_at' => null,
        ]);
    }

    private function sendHandoff(MeritInvoice $invoice, int $step, string $to): void
    {
        try {
            Mail::mailer($this->outreachMailer)->to($to)->send(new MeritHandoffMail($invoice, $step));
        } catch (\Throwable $e) {
            Log::error('[Merit] Teavituse saatmine ebaõnnestus', ['invoice' => $invoice->invoiceNo, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Arve PDF manusena (üks arve).
     *
     * @return array<int, array{name: string, content: string}>
     */
    private function buildInvoiceAttachment(MeritInvoice $inv): array
    {
        try {
            $sihId = $this->client->getInvoiceId($inv->invoiceNo, $inv->customerId ?: null);
            if (! $sihId) {
                return [];
            }
            $pdf = $this->client->getInvoicePdf($sihId);

            return $pdf ? [$pdf] : [];
        } catch (\Throwable $e) {
            Log::warning('[Merit] Arve PDF-i pärimine ebaõnnestus', ['invoice' => $inv->invoiceNo, 'error' => $e->getMessage()]);

            return [];
        }
    }

    private function log(MeritInvoice $inv, int $step, string $status, ?string $error = null): void
    {
        MeritReminderLog::create([
            'merit_customer_id' => $inv->customerId,
            'customer_name'     => $inv->customerName,
            'invoice_no'        => $inv->invoiceNo,
            'email'             => $inv->email,
            'level'             => $step,
            'overdue_days'      => $inv->daysOverdue,
            'total_unpaid'      => $inv->unpaid,
            'invoice_numbers'   => [$inv->invoiceNo],
            'status'            => $status,
            'error'             => $error,
            'sent_at'           => $status === 'sent' ? now() : null,
        ]);
    }

    // ─── Abifunktsioonid ────────────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function lookupCustomer(string $customerId): ?array
    {
        if (array_key_exists($customerId, $this->customerCache)) {
            return $this->customerCache[$customerId];
        }

        try {
            $customer = $this->client->getCustomer($customerId);
        } catch (\Throwable $e) {
            Log::warning('[Merit] Kliendi andmete pärimine ebaõnnestus', ['customer' => $customerId, 'error' => $e->getMessage()]);
            $customer = null;
        }

        return $this->customerCache[$customerId] = $customer;
    }

    private function parseMeritDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{8}$/', $value)) {
                return Carbon::createFromFormat('Ymd', $value)->startOfDay();
            }
            if (preg_match('/\/Date\((\d+)/', $value, $m)) {
                return Carbon::createFromTimestampMs((int) $m[1])->startOfDay();
            }

            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
