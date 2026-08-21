<?php

namespace App\Outreach\Jobs;

use App\Outreach\Services\ReplyDetectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
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
class CheckOutreachRepliesJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1; // Don't retry — next scheduled run will handle it
    public int $timeout = 120;

    // Only one copy in the queue at a time. IMAP checks can hang to the full
    // timeout; without this the every-5-min schedule piles up duplicates faster
    // than the workers can drain, jamming the whole outreach queue. uniqueFor
    // exceeds the timeout so the lock always clears even on a hard worker kill.
    public int $uniqueFor = 900;

    public function handle(ReplyDetectionService $service): void
    {
        Log::info('[Outreach] CheckOutreachRepliesJob starting');

        $detected = $service->checkAllAccounts();

        Log::info('[Outreach] CheckOutreachRepliesJob complete', ['new_replies' => $detected]);
    }
}
