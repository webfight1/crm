<?php

namespace App\Outreach\Jobs;

use App\Outreach\Services\ReplyDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * CheckOutreachRepliesJob
 *
 * Scheduled to run every 5 minutes.
 * Iterates over all active inboxes with IMAP configured and checks
 * for replies from leads. Marks replied leads as completed.
 */
class CheckOutreachRepliesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1; // Don't retry — next scheduled run will handle it
    public int $timeout = 120;

    public function handle(ReplyDetectionService $service): void
    {
        Log::info('[Outreach] CheckOutreachRepliesJob starting');

        $detected = $service->checkAllAccounts();

        Log::info('[Outreach] CheckOutreachRepliesJob complete', ['new_replies' => $detected]);
    }
}
