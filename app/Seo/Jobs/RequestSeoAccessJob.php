<?php

namespace App\Seo\Jobs;

use App\Outreach\Models\OutreachLead;
use App\Seo\Services\AccessRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Deal reached a trigger stage (access.trigger_stages) → ask for access. */
class RequestSeoAccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 60;

    public function __construct(public int $leadId)
    {
        $this->onQueue('outreach');
    }

    public function handle(AccessRequestService $access): void
    {
        if ($lead = OutreachLead::find($this->leadId)) {
            $access->request($lead);
        }
    }
}
