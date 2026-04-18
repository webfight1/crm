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

    public function handle(): void
    {
        $leads = $this->fetchReadyLeads();

        if ($leads->isEmpty()) {
            return;
        }

        Log::info('[Outreach] ProcessOutreachLeadsJob dispatching', ['count' => $leads->count()]);

        foreach ($leads as $lead) {
            // Random delay: 60–180 seconds (human-like spacing)
            $delaySeconds = random_int(60, 180);

            SendOutreachEmailJob::dispatch($lead->id)
                ->onQueue('outreach')
                ->delay(now()->addSeconds($delaySeconds));
        }
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
