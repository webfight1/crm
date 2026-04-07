<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachLeadEvent;

/**
 * OutreachAuditService
 *
 * Thin wrapper around OutreachLeadEvent::create() that provides named
 * methods for each event type. Keeps audit-write calls readable at call
 * sites and ensures consistent metadata shapes.
 *
 * All methods are fire-and-forget — failures are intentionally swallowed
 * after logging so that an audit write never kills a send job.
 */
class OutreachAuditService
{
    public function enrolled(int $leadId, int $campaignId): void
    {
        $this->write($leadId, OutreachLeadEvent::TYPE_ENROLLED, [
            'campaign_id' => $campaignId,
        ]);
    }

    public function stepSent(int $leadId, int $stepOrder, string $messageId, int $accountId): void
    {
        $this->write($leadId, OutreachLeadEvent::TYPE_STEP_SENT, [
            'step_order'  => $stepOrder,
            'message_id'  => $messageId,
            'account_id'  => $accountId,
        ]);
    }

    public function stepFailed(int $leadId, int $stepOrder, string $error, int $accountId): void
    {
        $this->write($leadId, OutreachLeadEvent::TYPE_STEP_FAILED, [
            'step_order' => $stepOrder,
            'error'      => $error,
            'account_id' => $accountId,
        ]);
    }

    public function stepSkipped(int $leadId, int $stepOrder, string $reason): void
    {
        $this->write($leadId, OutreachLeadEvent::TYPE_STEP_SKIPPED, [
            'step_order' => $stepOrder,
            'reason'     => $reason,
        ]);
    }

    public function replyDetected(int $leadId, string $strategy, ?string $messageId = null): void
    {
        $this->write($leadId, OutreachLeadEvent::TYPE_REPLY_DETECTED, array_filter([
            'strategy'   => $strategy,   // 'message_id' | 'sender_address'
            'message_id' => $messageId,
        ]));
    }

    public function bounced(int $leadId, string $source, string $error): void
    {
        $this->write($leadId, OutreachLeadEvent::TYPE_BOUNCED, [
            'source' => $source,   // 'smtp_5xx' | 'ndr_scan'
            'error'  => $error,
        ]);
    }

    public function statusChanged(int $leadId, string $from, string $to, string $reason): void
    {
        $this->write($leadId, OutreachLeadEvent::TYPE_STATUS_CHANGED, [
            'from'   => $from,
            'to'     => $to,
            'reason' => $reason,
        ]);
    }

    public function sequenceCompleted(int $leadId, int $totalSteps): void
    {
        $this->write($leadId, OutreachLeadEvent::TYPE_SEQUENCE_COMPLETED, [
            'total_steps' => $totalSteps,
        ]);
    }

    // ─── Internal ───────────────────────────────────────────────────────────

    private function write(int $leadId, string $eventType, array $metadata = []): void
    {
        try {
            OutreachLeadEvent::create([
                'lead_id'    => $leadId,
                'event_type' => $eventType,
                'metadata'   => $metadata ?: null,
            ]);
        } catch (\Throwable $e) {
            // Audit writes must never kill a send job. Log and continue.
            \Illuminate\Support\Facades\Log::error('[Outreach] Audit write failed', [
                'lead_id'    => $leadId,
                'event_type' => $eventType,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
