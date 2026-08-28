<?php

namespace App\Outreach\Jobs;

use App\Outreach\Models\OutreachLead;
use App\Outreach\Services\OutreachDraftGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs OutreachDraftGeneratorService::generate() for one lead on the
 * queue worker. Dispatched in bulk by the "Generate batch" button and
 * from the CLI command so the operator can close the browser tab and
 * let the fleet finish in the background.
 *
 * Uses the 'outreach' queue that the existing worker already services
 * (queue:work --queue=outreach,default). Sequential per-lead so each
 * OpenAI call is metered rather than fired in parallel — keeps us well
 * under gpt-4o-mini rate limits and avoids overwhelming any single lead
 * site with a burst.
 */
class GenerateDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;   // Draft-gen already writes its own failure state
    public int $timeout = 90;  // Fetch (8s) + OpenAI (up to 45s) + slack

    public function __construct(public int $leadId)
    {
        $this->onQueue('outreach');
    }

    public function handle(OutreachDraftGeneratorService $gen): void
    {
        $lead = OutreachLead::find($this->leadId);
        if (! $lead) return;
        $gen->generate($lead);
    }
}
