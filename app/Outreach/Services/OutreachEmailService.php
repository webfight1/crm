<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachCampaignStep;
use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachSendLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

/**
 * OutreachEmailService
 *
 * Orchestrates a single outreach send for one lead.
 *
 * ── Timing constants ────────────────────────────────────────────────────────
 *
 * PENDING_LOG_STALE_SECONDS (300)
 *   A pending send log is "stale" when it is older than this value. Stale logs
 *   were created by a worker that died between the INSERT and the SMTP call —
 *   meaning the email was never actually sent. The threshold must be strictly
 *   greater than SendOutreachEmailJob::$timeout (60s). 300s = 5× the timeout,
 *   providing ample margin for OS scheduling delays and slow TCP handshakes.
 *
 * CAPACITY_RETRY_OFFSET_MINUTES (2)
 *   When all inboxes are at their daily limit, the lead is deferred to the
 *   next capacity reset (midnight). This offset pushes the retry 2 minutes
 *   past midnight, ensuring ResetOutreachDailyLimitsJob has had time to run
 *   before ProcessOutreachLeadsJob picks the lead up again.
 */
class OutreachEmailService
{
    private const PENDING_LOG_STALE_SECONDS     = 300;
    private const CAPACITY_RETRY_OFFSET_MINUTES = 2;

    public function __construct(
        private readonly OutreachMailer       $mailer,
        private readonly InboxRotationService $rotation,
        private readonly OutreachAuditService $audit,
        private readonly LoggerInterface      $logger,
    ) {}

    /**
     * Send the next sequence step to a lead.
     *
     * @return bool  true = sent, false = skipped for a non-error reason.
     * @throws Throwable  on send failure or stale-log recovery (triggers job retry).
     */
    public function sendNextStep(OutreachLead $lead): bool
    {
        $lead->loadMissing('campaign');
        $campaign = $lead->campaign;

        // ── STOP GUARD ──────────────────────────────────────────────────────
        if (! $this->passesSendGuards($lead, $campaign)) {
            $lead->releaseProcessingLock();
            return false;
        }

        // ── STEP RESOLUTION ─────────────────────────────────────────────────
        // current_step = index of the last step sent (0 = nothing sent yet).
        // The step to send is always current_step + 1.
        $stepOrder = $lead->current_step + 1;
        $step      = $campaign->getStepAt($stepOrder);

        if (! $step) {
            $this->logger->info('[Outreach] No step at order, marking lead completed', [
                'lead_id'    => $lead->id,
                'step_order' => $stepOrder,
            ]);
            $lead->markCompleted();
            $lead->releaseProcessingLock();
            return false;
        }

        // ── INBOX SELECTION (reserves capacity atomically) ───────────────────
        $account = $this->rotation->selectInbox($lead, $campaign);

        if (! $account) {
            // All inboxes are at their daily limit. Deferring to the next capacity
            // reset prevents this lead from re-entering the queue every minute
            // until midnight (which would generate thousands of no-op jobs).
            //
            // next_send_at = tomorrow 00:00 + CAPACITY_RETRY_OFFSET_MINUTES
            //
            // The offset ensures ResetOutreachDailyLimitsJob has completed before
            // ProcessOutreachLeadsJob picks this lead up again.
            //
            // If it is currently 00:30 and inboxes are already saturated today,
            // addDay()->startOfDay() correctly targets the following midnight
            // (~23.5 hours away) rather than the midnight that has already passed.
            $retryAt = now()->addDay()->startOfDay()->addMinutes(self::CAPACITY_RETRY_OFFSET_MINUTES);

            $lead->update(['next_send_at' => $retryAt]);
            $lead->releaseProcessingLock();

            $this->logger->info('[Outreach] No inbox capacity, lead deferred to next reset', [
                'lead_id'  => $lead->id,
                'retry_at' => $retryAt->toDateTimeString(),
            ]);

            return false;
        }

        // ── IDEMPOTENCY GUARD ────────────────────────────────────────────────
        // Key is unique per lead+step. A constraint violation means a previous
        // attempt left a record behind. Resolve without sending a duplicate.
        $idempotencyKey  = "l{$lead->id}_s{$step->id}";
        $renderedSubject = $step->renderSubject($lead);
        $renderedBody    = $step->renderBody($lead);

        try {
            $log = OutreachSendLog::create([
                'lead_id'          => $lead->id,
                'campaign_id'      => $campaign->id,
                'email_account_id' => $account->id,
                'campaign_step_id' => $step->id,
                'step_order'       => $step->step_order,
                'to_email'         => $lead->email,
                'from_email'       => $account->email,
                'subject'          => $renderedSubject,
                'body'             => $renderedBody,
                'status'           => OutreachSendLog::STATUS_PENDING,
                'idempotency_key'  => $idempotencyKey,
            ]);
        } catch (UniqueConstraintViolationException) {
            // NOTE: recoverFromIdempotencyConflict() may throw a RuntimeException
            // (stale-log recovery path). That exception is NOT caught by the
            // catch(Throwable) block below — they are sequential, not nested.
            // It propagates up to SendOutreachEmailJob::handle() → queue retry.
            return $this->recoverFromIdempotencyConflict($lead, $step, $idempotencyKey, $campaign);
        }

        // ── SEND ─────────────────────────────────────────────────────────────
        try {
            $messageId = $this->mailer->send(
                account:  $account,
                toEmail:  $lead->email,
                toName:   trim("{$lead->first_name} " . ($lead->last_name ?? '')),
                subject:  $renderedSubject,
                htmlBody: $renderedBody,
            );

            $log->markSent($messageId);
            // sent_today was pre-incremented atomically by InboxRotationService.
            // Do not call $account->incrementSentToday() here.

            // A successful send clears any accumulated failure count.
            $account->resetFailureCount();

            $this->audit->stepSent($lead->id, $step->step_order, $messageId, $account->id);

            $this->afterSuccessfulSend($lead, $campaign);

            $this->logger->info('[Outreach] Email sent successfully', [
                'lead_id'    => $lead->id,
                'account_id' => $account->id,
                'step_order' => $step->step_order,
                'message_id' => $messageId,
            ]);

            return true;

        } catch (TransportExceptionInterface $e) {
            // A permanent 5xx rejection is a hard bounce: the address does not
            // exist or the remote server has permanently refused delivery.
            // Do not retry — mark the lead bounced and release the lock.
            if ($this->isPermanentSmtpFailure($e)) {
                $log->markFailed($e->getMessage());
                $lead->markBounced();
                $lead->releaseProcessingLock();

                $this->audit->bounced($lead->id, 'smtp_5xx', $e->getMessage());

                $this->logger->warning('[Outreach] Hard bounce detected, lead marked bounced', [
                    'lead_id'    => $lead->id,
                    'account_id' => $account->id,
                    'error'      => $e->getMessage(),
                ]);

                return false;
            }

            // Transient transport failure — record against inbox health, then retry
            $log->markFailed($e->getMessage());
            $account->recordFailure($e->getMessage());
            $this->audit->stepFailed($lead->id, $step->step_order, $e->getMessage(), $account->id);

            $this->logger->error('[Outreach] Email send failed (transient), will retry', [
                'lead_id'    => $lead->id,
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
            ]);

            throw $e;

        } catch (Throwable $e) {
            // markFailed nulls the idempotency_key so the next retry can
            // INSERT a fresh pending record with the same key.
            $log->markFailed($e->getMessage());

            // Do NOT release the processing lock here. Releasing it would allow
            // ProcessOutreachLeadsJob to re-dispatch this lead while the current
            // job's retry backoff is in progress — creating two concurrent jobs.
            // The lock is released only by SendOutreachEmailJob::failed() after
            // all retry attempts are exhausted.

            $this->logger->error('[Outreach] Email send failed, will retry', [
                'lead_id'    => $lead->id,
                'account_id' => $account->id,
                'error'      => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ─── Private Helpers ────────────────────────────────────────────────────

    private function passesSendGuards(OutreachLead $lead, OutreachCampaign $campaign): bool
    {
        if ($lead->replied) {
            $this->logger->info('[Outreach] Lead has replied, skipping', ['lead_id' => $lead->id]);
            return false;
        }

        if ($lead->status !== OutreachLead::STATUS_ACTIVE) {
            $this->logger->info('[Outreach] Lead not active, skipping', [
                'lead_id' => $lead->id,
                'status'  => $lead->status,
            ]);
            return false;
        }

        if (! $campaign->is_active) {
            $this->logger->info('[Outreach] Campaign inactive, skipping', ['campaign_id' => $campaign->id]);
            return false;
        }

        return true;
    }

    private function afterSuccessfulSend(OutreachLead $lead, OutreachCampaign $campaign): void
    {
        $hasMore = $lead->advanceToNextStep($campaign);
        $lead->releaseProcessingLock();

        if (! $hasMore) {
            $this->audit->sequenceCompleted($lead->id, $lead->current_step);
        }
    }

    /**
     * Resolve a UniqueConstraintViolationException on the idempotency_key.
     *
     * A conflict means a previous job attempt left a pending or sent record
     * behind. There are three distinct sub-cases:
     *
     * ── status = 'sent' ──────────────────────────────────────────────────────
     *   The email was physically delivered but the worker died before
     *   advanceToNextStep() ran. Recover by advancing state now. No resend.
     *
     * ── status = 'pending', log is recent (< PENDING_LOG_STALE_SECONDS) ─────
     *   A concurrent worker genuinely has this lead in-flight. The
     *   processing_since lock should prevent this scenario in practice, but
     *   if it occurs, bail safely: release the lock and return false.
     *
     * ── status = 'pending', log is stale (>= PENDING_LOG_STALE_SECONDS) ─────
     *   The previous worker died between INSERT and mailer->send(). The email
     *   was NOT sent. Clear the idempotency_key to free the uniqueness slot,
     *   then throw to force the queue to retry this job. The processing lock
     *   is intentionally kept held to prevent ProcessOutreachLeadsJob from
     *   re-dispatching during the retry backoff window.
     *
     * ── $existing is null ────────────────────────────────────────────────────
     *   The key was nulled out (by markFailed) in the microseconds between
     *   the INSERT failure and this SELECT. The slot is free; let the job
     *   retry on the next backoff interval.
     */
    private function recoverFromIdempotencyConflict(
        OutreachLead         $lead,
        OutreachCampaignStep $step,
        string               $idempotencyKey,
        OutreachCampaign     $campaign,
    ): bool {
        $existing = OutreachSendLog::where('idempotency_key', $idempotencyKey)->first();

        // ── Key was released between the violation and this lookup ───────────
        if (! $existing) {
            $lead->releaseProcessingLock();
            return false;
        }

        // ── Email was already delivered — just advance state ─────────────────
        if ($existing->status === OutreachSendLog::STATUS_SENT) {
            $this->logger->warning('[Outreach] Idempotency recovery: email already delivered, advancing state', [
                'lead_id'    => $lead->id,
                'step_order' => $step->step_order,
                'log_id'     => $existing->id,
            ]);
            $this->afterSuccessfulSend($lead, $campaign);
            return true;
        }

        // ── status = 'pending': determine whether in-flight or stale ─────────
        $ageSeconds = now()->diffInSeconds($existing->created_at);

        if ($ageSeconds < self::PENDING_LOG_STALE_SECONDS) {
            // Recent — treat as genuinely in-flight and bail
            $this->logger->warning('[Outreach] Idempotency conflict: lead+step in-flight', [
                'lead_id'         => $lead->id,
                'step_order'      => $step->step_order,
                'existing_log_id' => $existing->id,
                'log_age_seconds' => $ageSeconds,
            ]);
            $lead->releaseProcessingLock();
            return false;
        }

        // Stale pending: previous worker died before the SMTP call.
        // The email was NOT sent. Clear the key so the retry can INSERT
        // a fresh pending log, then throw to trigger the retry.
        //
        // The processing lock is deliberately NOT released here.
        // Keeping it held ensures ProcessOutreachLeadsJob cannot re-dispatch
        // this lead while the retry backoff (60s) is in progress.
        $this->logger->warning('[Outreach] Clearing stale pending log, forcing retry', [
            'lead_id'         => $lead->id,
            'step_order'      => $step->step_order,
            'existing_log_id' => $existing->id,
            'log_age_seconds' => $ageSeconds,
            'stale_threshold' => self::PENDING_LOG_STALE_SECONDS,
        ]);

        $existing->update(['idempotency_key' => null]);

        throw new \RuntimeException(sprintf(
            '[Outreach] Stale pending log #%d cleared for lead %d step %d. Retrying send.',
            $existing->id,
            $lead->id,
            $step->step_order,
        ));
    }

    /**
     * Return true if the transport exception represents a permanent SMTP 5xx failure.
     *
     * Symfony wraps the raw SMTP dialogue in the exception message. We scan for
     * the canonical 5xx reply codes that indicate a hard bounce:
     *   550 — Mailbox not found / does not exist
     *   551 — User not local / forwarding refused
     *   552 — Mailbox full (permanent over-quota — treat as hard bounce)
     *   553 — Mailbox name not allowed
     *   554 — Transaction failed (catch-all permanent rejection)
     *
     * 421 / 450 / 451 / 452 are transient (4xx) and should be retried.
     */
    private function isPermanentSmtpFailure(TransportExceptionInterface $e): bool
    {
        // Symfony\Component\Mailer\Exception\TransportException exposes getCode()
        // for some errors, but the most reliable signal is the message text which
        // contains the raw SMTP response line.
        $message = $e->getMessage();
        return (bool) preg_match('/\b5[5-9]\d\b/', $message);
    }
}
