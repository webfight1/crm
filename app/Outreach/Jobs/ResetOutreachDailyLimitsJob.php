<?php

namespace App\Outreach\Jobs;

use App\Outreach\Models\OutreachEmailAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ResetOutreachDailyLimitsJob
 *
 * Scheduled to run once at midnight every day.
 * Resets sent_today = 0 on all email accounts so each inbox
 * starts the new day with its full daily sending capacity.
 */
class ResetOutreachDailyLimitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function handle(): void
    {
        $count = OutreachEmailAccount::where('sent_today', '>', 0)->count();

        OutreachEmailAccount::query()->update(['sent_today' => 0]);

        Log::info('[Outreach] Daily limits reset', ['accounts_reset' => $count]);
    }
}
