<?php

namespace App\Outreach\Jobs;

use App\Outreach\Models\OutreachLead;
use App\Outreach\Services\OutreachEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * SendOutreachEmailJob
 *
 * Sends a single email to one outreach lead.
 * Dispatched by ProcessOutreachLeadsJob with a random delay.
 *
 * Retry policy:
 *  - 3 attempts total
 *  - Exponential back-off: 1 min → 5 min → 15 min
 *  - On final failure the lead's processing lock is released so
 *    the scheduler can retry it on the next cycle.
 */
class SendOutreachEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct(private readonly int $leadId) {}

    public function handle(OutreachEmailService $service): void
    {
        $lead = OutreachLead::find($this->leadId);

        if (! $lead) {
            Log::warning('[Outreach] SendOutreachEmailJob: lead not found', ['lead_id' => $this->leadId]);
            return;
        }

        // Final guard: re-check stop conditions after the random delay has elapsed.
        // The lead's state could have changed (manual pause, replied flag, etc.)
        if (! $lead->isReadyToSend()) {
            Log::info('[Outreach] Lead no longer ready to send after delay, releasing lock', [
                'lead_id' => $lead->id,
                'status'  => $lead->status,
                'replied' => $lead->replied,
            ]);
            $lead->releaseProcessingLock();
            return;
        }

        $service->sendNextStep($lead);
    }

    /**
     * Calculate exponential back-off between retries.
     * Attempt 1 → 60s, Attempt 2 → 300s, Attempt 3 → 900s
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /**
     * Called after all retry attempts are exhausted.
     *
     * If the final exception is a permanent SMTP 5xx rejection (hard bounce),
     * mark the lead as bounced — it should not be retried further.
     * In all other cases, release the processing lock so the lead re-enters
     * the queue on the next scheduler cycle.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('[Outreach] SendOutreachEmailJob permanently failed', [
            'lead_id' => $this->leadId,
            'error'   => $exception->getMessage(),
        ]);

        $lead = OutreachLead::find($this->leadId);
        if (! $lead) {
            return;
        }

        // A permanent SMTP rejection reaching failed() means all three retry
        // attempts saw the same 5xx code. The send service normally handles
        // this inline (returns false, no throw), but guard defensively here
        // in case the exception escapes a future code path.
        if ($exception instanceof TransportExceptionInterface
            && preg_match('/\b5[5-9]\d\b/', $exception->getMessage())
        ) {
            $lead->markBounced();
            $lead->releaseProcessingLock();

            Log::warning('[Outreach] Lead marked bounced after permanent SMTP failure', [
                'lead_id' => $this->leadId,
            ]);

            return;
        }

        $lead->releaseProcessingLock();
    }
}
