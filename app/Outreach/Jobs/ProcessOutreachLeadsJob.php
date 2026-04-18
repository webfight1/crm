<?php

namespace App\Outreach\Jobs;

use App\Outreach\Models\OutreachLead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcessOutreachLeadsJob
 *
 * Scheduled to run every minute.
 * Finds all leads that are ready to send, acquires a processing lock,
 * and dispatches a SendOutreachEmailJob for each with a random human-like delay.
 *
 * Lock strategy:
 *  - `processing_since` is set immediately inside a locked transaction.
 *  - Stale locks older than 10 minutes are automatically released so a
 *    failed job never blocks a lead permanently.
 */
class ProcessOutreachLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Max time a lead can be in "processing" before its lock is considered stale
    const STALE_LOCK_MINUTES = 10;

    // Per-send random delay bounds (seconds). 3–10 min keeps each outgoing
    // message spaced naturally instead of arriving in visible bursts.
    const MIN_SEND_DELAY_SECONDS = 180;
    const MAX_SEND_DELAY_SECONDS = 600;

    // Working-hours window — cold-outreach best practice is to send only during
    // business hours in the recipient's region. Outside this window the cron
    // keeps running but no new jobs are dispatched; leads simply wait.
    const WORK_HOUR_START = 9;   // inclusive (09:00)
    const WORK_HOUR_END   = 17;  // exclusive (last dispatch minute is 16:59)
    // Days-of-week allowed (ISO: 1=Mon … 7=Sun)
    const WORK_DAYS = [1, 2, 3, 4, 5];

    public function handle(): void
    {
        if (! $this->isWithinWorkingHours()) {
            return;
        }

        $leads = $this->fetchReadyLeads();

        if ($leads->isEmpty()) {
            return;
        }

        Log::info('[Outreach] ProcessOutreachLeadsJob dispatching', ['count' => $leads->count()]);

        foreach ($leads as $lead) {
            $delaySeconds = random_int(self::MIN_SEND_DELAY_SECONDS, self::MAX_SEND_DELAY_SECONDS);

            SendOutreachEmailJob::dispatch($lead->id)
                ->onQueue('outreach')
                ->delay(now()->addSeconds($delaySeconds));
        }
    }

    /**
     * Whether "right now" (app timezone) is inside the outbound sending window.
     */
    private function isWithinWorkingHours(): bool
    {
        $now = now();
        return in_array($now->dayOfWeekIso, self::WORK_DAYS, true)
            && $now->hour >= self::WORK_HOUR_START
            && $now->hour <  self::WORK_HOUR_END;
    }

    private function fetchReadyLeads()
    {
        return DB::transaction(function () {
            $staleCutoff = now()->subMinutes(self::STALE_LOCK_MINUTES);

            $leads = OutreachLead::where('status', OutreachLead::STATUS_ACTIVE)
                ->where('replied', false)
                ->where('qualification', '!=', OutreachLead::QUALIFICATION_SKIP)
                ->where('next_send_at', '<=', now())
                ->where(function ($q) use ($staleCutoff) {
                    // Not currently being processed, or lock has gone stale
                    $q->whereNull('processing_since')
                      ->orWhere('processing_since', '<', $staleCutoff);
                })
                ->lockForUpdate()
                ->get();

            // Acquire locks immediately to prevent duplicate dispatch
            foreach ($leads as $lead) {
                $lead->acquireProcessingLock();
            }

            return $leads;
        });
    }
}
