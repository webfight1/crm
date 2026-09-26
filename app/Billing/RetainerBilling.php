<?php

namespace App\Billing;

use App\Models\Deal;
use App\Models\Task;
use App\Support\Telegram;
use Illuminate\Support\Carbon;

/**
 * Monthly invoices of „Püsiklient“ deals (revenue_model = retainer). The CRM
 * doesn't invoice: on the invoice day of each month of the period it adds a
 * task to the deal and sends Telegram the RMP link that opens that month's
 * invoice (rmp …/invoices/from-crm?deal=ID&month=YYYY-MM). After the last month
 * it asks once whether to extend. The task doubles as the "already reminded"
 * mark, so the daily run never repeats a month.
 */
class RetainerBilling
{
    /** Stages in which no monthly invoices are due. */
    public const STOPPED = ['valmis', 'arveldatud', 'closed_lost', 'tühistatud'];

    /**
     * Where a deal is in its period on $day.
     *
     * @return array{month:int, of:?int, period:string, next:?Carbon, ended:bool}|null  null = not a monthly deal / not started
     */
    public static function status(Deal $deal, ?Carbon $day = null): ?array
    {
        if ($deal->revenue_model !== 'retainer' || ! $deal->retainer_amount || ! $deal->retainer_start) {
            return null;
        }
        $day = ($day ?? now())->copy()->startOfDay();
        $start = Carbon::parse($deal->retainer_start)->startOfMonth();
        if ($day->lt($start)) {
            return ['month' => 0, 'of' => $deal->retainer_months, 'period' => $start->format('Y-m'), 'next' => self::invoiceDay($deal, $start), 'ended' => false];
        }

        $month = (int) $start->diffInMonths($day->copy()->startOfMonth()) + 1;
        $ended = $deal->retainer_months && $month > $deal->retainer_months;
        $current = $day->copy()->startOfMonth();
        $next = self::invoiceDay($deal, $current);
        if ($next->lt($day)) {
            $next = self::invoiceDay($deal, $current->copy()->addMonth());
        }
        if ($deal->retainer_months && $next->copy()->startOfMonth()->gt($start->copy()->addMonths($deal->retainer_months - 1))) {
            $next = null;
        }

        return ['month' => $month, 'of' => $deal->retainer_months, 'period' => $current->format('Y-m'), 'next' => $next, 'ended' => $ended];
    }

    /** The invoice day in a month — the 31st becomes the month's last day. */
    public static function invoiceDay(Deal $deal, Carbon $month): Carbon
    {
        $m = $month->copy()->startOfMonth();

        return $m->day(min(max(1, (int) ($deal->retainer_day ?: 1)), $m->daysInMonth));
    }

    public static function rmpUrl(Deal $deal, string $period): string
    {
        return rtrim((string) config('services.rmp.url'), '/') . '/invoices/from-crm?deal=' . $deal->id . '&month=' . $period;
    }

    /** The daily run. @return int reminders sent */
    public function run(?Carbon $day = null): int
    {
        $day = ($day ?? now())->copy()->startOfDay();
        $sent = 0;

        $deals = Deal::where('revenue_model', 'retainer')->where('retainer_amount', '>', 0)
            ->whereNotNull('retainer_start')->whereNotIn('stage', self::STOPPED)->get();

        foreach ($deals as $deal) {
            $s = self::status($deal, $day);
            if (! $s || $s['month'] < 1) {
                continue;
            }
            if ($s['ended']) {
                $sent += (int) $this->endNotice($deal);
                continue;
            }
            if ($day->gte(self::invoiceDay($deal, $day))) {
                $sent += (int) $this->monthReminder($deal, $s);
            }
        }

        return $sent;
    }

    private function monthReminder(Deal $deal, array $s): bool
    {
        $label = Carbon::createFromFormat('Y-m-d', $s['period'] . '-01')->format('m/Y');
        $title = "Kuuarve {$label}: {$deal->title}";
        if (Task::where('deal_id', $deal->id)->where('title', $title)->exists()) {
            return false;
        }

        $amount = number_format((float) $deal->retainer_amount, 2, ',', ' ') . ' €';
        $count = $s['of'] ? " ({$s['month']}/{$s['of']})" : " ({$s['month']}. kuu)";
        $rmp = self::rmpUrl($deal, $s['period']);

        $this->task($deal, $title, "Tee RMP-s selle kuu arve: {$rmp}\n"
            . ($deal->retainer_note ? "Rida: {$deal->retainer_note}\n" : '') . "Summa: {$amount} + KM{$count}");

        Telegram::send(
            "🧾 Kuuarve {$label} (" . config('app.name') . ")\n"
            . "{$deal->title} · {$amount}{$count}\n"
            . "Loo arve RMP-s: {$rmp}\n"
            . 'Tehing: ' . route('deals.show', $deal)
        );

        return true;
    }

    private function endNotice(Deal $deal): bool
    {
        $title = "Kuutasu periood lõppes: {$deal->title}";
        if (Task::where('deal_id', $deal->id)->where('title', $title)->exists()) {
            return false;
        }

        $this->task($deal, $title, "Viimane kuu oli {$deal->retainer_months}. Pikenda (tehingus „Kuude arv“) või lõpeta tehing.");
        Telegram::send(
            "⏳ Kuutasu periood lõppes (" . config('app.name') . ")\n"
            . "{$deal->title} · {$deal->retainer_months} kuud. Kas pikendad?\n"
            . 'Tehing: ' . route('deals.edit', $deal)
        );

        return true;
    }

    private function task(Deal $deal, string $title, string $description): void
    {
        Task::create([
            'title'       => mb_substr($title, 0, 255),
            'description' => $description,
            'type'        => 'other',
            'priority'    => 'high',
            'status'      => 'pending',
            'due_date'    => now(),
            'customer_id' => $deal->customer_id,
            'company_id'  => $deal->company_id,
            'deal_id'     => $deal->id,
            'user_id'     => $deal->user_id,
            'assignee_id' => $deal->user_id,
            'price'       => 0,
        ]);
    }
}
