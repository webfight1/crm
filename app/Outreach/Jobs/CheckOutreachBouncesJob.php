<?php

namespace App\Outreach\Jobs;

use App\Outreach\Services\BounceDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * CheckOutreachBouncesJob
 *
 * Scheduled to run every 15 minutes.
 * Scans each active inbox for Non-Delivery Reports (NDRs) and marks
 * the affected leads as bounced so they are excluded from future sends.
 */
class CheckOutreachBouncesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1; // Don't retry — next scheduled run will handle it
    public int $timeout = 120;

    // Only one copy in the queue at a time — see CheckOutreachRepliesJob. IMAP
    // scans can hang to the timeout; uniqueness stops the every-15-min schedule
    // from piling up duplicates and jamming the outreach queue.
    public int $uniqueFor = 900;

    public function handle(BounceDetectionService $service): void
    {
        Log::info('[Outreach] CheckOutreachBouncesJob starting');

        $bounced = $service->checkAllAccounts();

        Log::info('[Outreach] CheckOutreachBouncesJob complete', ['bounced' => $bounced]);
    }
}
