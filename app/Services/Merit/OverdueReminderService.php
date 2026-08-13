<?php

namespace App\Services\Merit;

use App\Mail\OverdueReminderMail;
use App\Models\MeritDebtorState;
use App\Models\MeritReminderLog;
use App\Models\MeritReminderSetting;
use App\Outreach\Models\OutreachEmailAccount;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * OverdueReminderService — kogub Meritist üle tähtaja võlgnikud ja saadab
 * neile astmelised meeldetuletused (kuni 3), vältides kordust.
 *
 * Kirjad saadetakse läbi aktiivse outreach-postkasti SMTP (nt marius@kind.ee),
 * sama töötava seadistuse kaudu, mida outreach juba kasutab — nii pole eraldi
 * MAIL_* / parooli seadistust .env-is vaja.
 */
class OverdueReminderService
{
    /** @var array<string, array<string, mixed>|null> jooksu-cache getCustomer vastustele */
    private array $customerCache = [];

    /** Jooksu jooksul lahendatud saatja-maileri nimi (või null, kui pole kontot). */
    private ?string $outreachMailer = null;

    private bool $mailerResolved = false;

    public function __construct(
        private readonly MeritClient $client,
    ) {
    }

    /**
     * Aktiivne outreach-postkast, mille kaudu meeldetuletused saadetakse.
     * Eelistab primaarset vastuskontot, muidu esimest aktiivset SMTP-kontot.
     */
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

    /**
     * Seadistab käitusaegse maileri valitud outreach-konto SMTP-st ja tagastab
     * selle nime (või null, kui aktiivset kontot pole). Parool dekrüpteeritakse
     * mudeli accessoriga mälus. Seab ka globaalse "from" konto aadressiks, et
     * Gmail kirja vastu võtaks.
     */
    public function configureOutreachMailer(): ?string
    {
        if ($this->mailerResolved) {
            return $this->outreachMailer;
        }
        $this->mailerResolved = true;

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
                'password'   => $account->smtp_password, // dekrüpteeritud accessoriga
            ],
            'mail.from.address' => $account->email,
            'mail.from.name'    => MeritReminderSetting::getSettings()->from_name
                ?: ($account->name ?: config('app.name')),
        ]);

        return $this->outreachMailer = 'merit_outreach';
    }

    /**
     * Kogu praeguse seisu võlgnikud (grupeeritud kliendi kaupa), rikastatud
     * kontaktandmetega. Kasutatakse nii saatmisel kui UI eelvaates.
     *
     * @return Collection<int, MeritDebtor>
     */
    public function collectDebtors(): Collection
    {
        $rows = $this->client->getOverdueDebts(0);

        $grouped = collect($rows)
            // Ainult tegelikud võlad: tasumata summa > 0 ja mitte pakkumine (SO).
            ->filter(fn (array $r) => (float) ($r['UnPaidAmount'] ?? 0) > 0 && ($r['DocType'] ?? '') !== 'SO')
            ->groupBy(fn (array $r) => (string) ($r['PartnerId'] ?? $r['PartnerName'] ?? ''));

        return $grouped->map(function (Collection $rows, string $customerId): MeritDebtor {
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

                $overdue = $due ? max(0, $due->diffInDays($today, false)) : 0;
                $maxOverdue = max($maxOverdue, (int) $overdue);
                $total += $unpaid;

                $invoices[] = [
                    'doc_no'   => (string) ($r['DocNo'] ?? '—'),
                    'due_date' => $due,
                    'unpaid'   => $unpaid,
                    'currency' => $currency,
                ];
            }

            $customer = $customerId !== '' ? $this->lookupCustomer($customerId) : null;

            return new MeritDebtor(
                customerId: $customerId,
                name: $customer['Name'] ?? $partnerName,
                contact: $customer['Contact'] ?? null,
                email: $customer['Email'] ?? null,
                invoices: $invoices,
                totalUnpaid: round($total, 2),
                maxOverdueDays: $maxOverdue,
                currency: $currency,
            );
        })->values();
    }

    /**
     * Saada meeldetuletused. Dry-run korral ainult arvutab (ei saada, ei logi).
     *
     * @return array{sent: int, skipped: int, failed: int, cleared: int, planned: array<int, array<string, mixed>>}
     */
    public function sendReminders(bool $dryRun = false): array
    {
        $settings = MeritReminderSetting::getSettings();

        // Testrežiim: kui test-saaja on täidetud, lähevad KÕIK kirjad sinna
        // (mitte päris klientidele) ilma olekut/logisid muutmata.
        $testRecipient = filter_var((string) $settings->test_recipient, FILTER_VALIDATE_EMAIL) ?: null;
        $testMode = $testRecipient !== null;

        // Päris saatmiseks vaja aktiivset outreach-postkasti (SMTP).
        if (! $dryRun && $this->configureOutreachMailer() === null) {
            throw new \RuntimeException(
                'Saatmiseks pole aktiivset outreach-postkasti. Lisa/aktiveeri Postkastide all vähemalt üks SMTP-konto.'
            );
        }

        $debtors = $this->collectDebtors();

        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'cleared' => 0, 'planned' => []];

        $seenIds = [];

        foreach ($debtors as $debtor) {
            $seenIds[] = $debtor->customerId;

            // Testrežiimis kasutame värsket olekut, et varasem päris-saatmine
            // (min_days_between / juba-saadetud aste) testimist ei blokeeriks.
            $state = $testMode
                ? new MeritDebtorState(['merit_customer_id' => $debtor->customerId])
                : MeritDebtorState::firstOrNew(['merit_customer_id' => $debtor->customerId]);
            $level = $this->determineDueLevel($debtor, $settings, $state);

            if ($level === null) {
                $result['skipped']++;
                continue;
            }

            $plan = [
                'customer_id'  => $debtor->customerId,
                'name'         => $debtor->name,
                'email'        => $debtor->email,
                'level'        => $level,
                'overdue_days' => $debtor->maxOverdueDays,
                'total'        => $debtor->formattedTotal(),
            ];

            if (! $debtor->hasEmail()) {
                $plan['result'] = 'no_email';
                $result['planned'][] = $plan;
                $result['skipped']++;
                if (! $dryRun) {
                    $this->log($debtor, $level, 'skipped', 'E-posti aadress puudub');
                }
                continue;
            }

            if ($dryRun) {
                $plan['result'] = 'would_send';
                $result['planned'][] = $plan;
                $result['sent']++; // "saadaks" arv eelvaates
                continue;
            }

            try {
                $attachments = $this->buildAttachments($debtor, $settings);

                Mail::mailer($this->outreachMailer)
                    ->to($testMode ? $testRecipient : $debtor->email)
                    ->send(new OverdueReminderMail($debtor, $level, $settings, $attachments, $testMode));

                // Testrežiimis ei logi ega muuda olekut — korduvalt testitav.
                if (! $testMode) {
                    $this->log($debtor, $level, 'sent');
                    $state->fill([
                        'highest_level_sent' => $level,
                        'last_sent_at'       => now(),
                        'debt_cleared_at'    => null,
                    ])->save();
                }

                $plan['result'] = $testMode ? 'test_sent' : 'sent';
                $result['planned'][] = $plan;
                $result['sent']++;
            } catch (\Throwable $e) {
                Log::error('[Merit] Meeldetuletuse saatmine ebaõnnestus', [
                    'customer' => $debtor->customerId,
                    'error'    => $e->getMessage(),
                ]);
                $this->log($debtor, $level, 'failed', $e->getMessage());
                $plan['result'] = 'failed';
                $result['planned'][] = $plan;
                $result['failed']++;
            }
        }

        // Episoodi lõpetamine: kliendid, kes enam võlaraportis pole → nulli olek.
        // Testrežiimis olekut ei muudeta.
        if (! $dryRun && ! $testMode) {
            $result['cleared'] = $this->resetClearedDebtors($seenIds);
        }

        return $result;
    }

    /**
     * Eelvaade UI jaoks: iga võlgnik + arvutatud järgmine aste + olek.
     * Ei saada ega logi midagi.
     *
     * @return Collection<int, array{debtor: MeritDebtor, level: ?int, state: MeritDebtorState}>
     */
    public function previewPlan(?MeritReminderSetting $settings = null): Collection
    {
        $settings ??= MeritReminderSetting::getSettings();

        return $this->collectDebtors()->map(function (MeritDebtor $debtor) use ($settings) {
            $state = MeritDebtorState::firstOrNew(['merit_customer_id' => $debtor->customerId]);

            return [
                'debtor' => $debtor,
                'level'  => $this->determineDueLevel($debtor, $settings, $state),
                'state'  => $state,
            ];
        });
    }

    /**
     * Milline meeldetuletuse aste on nüüd võlgu (või null, kui midagi ei saada).
     */
    public function determineDueLevel(MeritDebtor $debtor, MeritReminderSetting $settings, MeritDebtorState $state): ?int
    {
        // NB! enabled-kontroll on käsu/kontrolleri tasemel — siin arvutame astme
        // ka väljalülitatud olekus, et UI eelvaade saaks seda näidata.
        if ($debtor->maxOverdueDays < $settings->min_overdue_days) {
            return null;
        }

        // Min vahe kahe kirja vahel.
        if ($state->last_sent_at && $state->last_sent_at->diffInDays(now()) < $settings->min_days_between) {
            return null;
        }

        $already = (int) $state->highest_level_sent;

        // Järgmine sisselülitatud aste, mille päevakünnis on täis ja mida pole veel saadetud.
        foreach ($settings->enabledSteps() as $level => $days) {
            if ($level > $already && $debtor->maxOverdueDays >= $days) {
                return $level;
            }
        }

        return null;
    }

    /**
     * Nulli olek klientidel, kes enam võlgu pole (võlg tasutud).
     *
     * @param  array<int, string>  $seenIds
     */
    private function resetClearedDebtors(array $seenIds): int
    {
        $query = MeritDebtorState::where('highest_level_sent', '>', 0)
            ->whereNull('debt_cleared_at');

        if (! empty($seenIds)) {
            $query->whereNotIn('merit_customer_id', $seenIds);
        }

        return $query->update([
            'highest_level_sent' => 0,
            'debt_cleared_at'    => now(),
        ]);
    }

    /**
     * Kogu võlgniku üle tähtaja arvete PDF-id manusteks (Meritist) — iga arve
     * eraldi PDF. max_attachments on turvapiir (0 = piiramata), et kaitsta
     * absurdselt suure kirja eest; tavaliselt lisatakse kõik arved.
     *
     * @return array<int, array{name: string, content: string}>
     */
    private function buildAttachments(MeritDebtor $debtor, MeritReminderSetting $settings): array
    {
        if (! $settings->attach_pdfs) {
            return [];
        }

        $max = (int) $settings->max_attachments; // 0 = piiramata
        $attachments = [];

        foreach ($debtor->invoices as $inv) {
            if ($max > 0 && count($attachments) >= $max) {
                break; // turvapiir täis
            }
            try {
                $sihId = $this->client->getInvoiceId((string) $inv['doc_no'], $debtor->customerId ?: null);
                if (! $sihId) {
                    continue;
                }
                $pdf = $this->client->getInvoicePdf($sihId);
                if ($pdf) {
                    $attachments[] = $pdf;
                }
            } catch (\Throwable $e) {
                Log::warning('[Merit] Arve PDF-i pärimine ebaõnnestus', [
                    'doc'   => $inv['doc_no'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $attachments;
    }

    private function log(MeritDebtor $debtor, int $level, string $status, ?string $error = null): void
    {
        MeritReminderLog::create([
            'merit_customer_id' => $debtor->customerId,
            'customer_name'     => $debtor->name,
            'email'             => $debtor->email,
            'level'             => $level,
            'overdue_days'      => $debtor->maxOverdueDays,
            'total_unpaid'      => $debtor->totalUnpaid,
            'invoice_numbers'   => collect($debtor->invoices)->pluck('doc_no')->all(),
            'status'            => $status,
            'error'             => $error,
            'sent_at'           => $status === 'sent' ? now() : null,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function lookupCustomer(string $customerId): ?array
    {
        if (array_key_exists($customerId, $this->customerCache)) {
            return $this->customerCache[$customerId];
        }

        try {
            $customer = $this->client->getCustomer($customerId);
        } catch (\Throwable $e) {
            Log::warning('[Merit] Kliendi andmete pärimine ebaõnnestus', [
                'customer' => $customerId,
                'error'    => $e->getMessage(),
            ]);
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
            // Kujul yyyyMMdd (nt "20220501").
            if (preg_match('/^\d{8}$/', $value)) {
                return Carbon::createFromFormat('Ymd', $value)->startOfDay();
            }

            // Kujul /Date(1656028800000)/ (Microsoft JSON).
            if (preg_match('/\/Date\((\d+)/', $value, $m)) {
                return Carbon::createFromTimestampMs((int) $m[1])->startOfDay();
            }

            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
