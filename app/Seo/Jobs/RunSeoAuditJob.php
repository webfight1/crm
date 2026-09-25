<?php

namespace App\Seo\Jobs;

use App\Seo\Models\SeoAudit;
use App\Seo\Services\SeoAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Manual "audit this URL" from the UI. */
class RunSeoAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300; // page finder + audit

    public function __construct(public int $auditId)
    {
        $this->onQueue('outreach');
    }

    public function handle(SeoAuditService $service): void
    {
        if ($audit = SeoAudit::find($this->auditId)) {
            $service->run($audit);
        }
    }
}
