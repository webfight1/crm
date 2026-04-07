<?php

namespace App\Outreach\Jobs;

use App\Outreach\Services\BounceDetectionService;
use Illuminate\Bus\Queueable;
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
class CheckOutreachBouncesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1; // Don't retry — next scheduled run will handle it
    public int $timeout = 120;

    public function handle(BounceDetectionService $service): void
    {
        Log::info('[Outreach] CheckOutreachBouncesJob starting');

        $bounced = $service->checkAllAccounts();

        Log::info('[Outreach] CheckOutreachBouncesJob complete', ['bounced' => $bounced]);
    }
}
