<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachEmailAccount;
use App\Outreach\Models\OutreachLead;
use Illuminate\Support\Facades\DB;
use Psr\Log\LoggerInterface;

/**
 * InboxRotationService
 *
 * Selects the most appropriate sending inbox for a given lead AND atomically
 * reserves one slot from that inbox's daily capacity.
 *
 * ── Concurrency contract ────────────────────────────────────────────────────
 * The original design had a TOCTOU race: the capacity check (sent_today < daily_limit)
 * and the increment (sent_today + 1) were in separate operations. Two workers could
 * both pass the check before either committed the increment, causing over-limit sends.
 *
 * Fix: reserveCapacity() holds an exclusive row lock (SELECT ... FOR UPDATE) and
 * writes the increment in the same transaction, before the lock is released.
 * No other worker can read or modify sent_today between those two operations.
 *
 * The returned account already has sent_today incremented. Callers must NOT
 * call incrementSentToday() again.
 *
 * ── Selection order ─────────────────────────────────────────────────────────
 *  1. Sticky: re-use the account already assigned to this lead (if it has capacity)
 *  2. Campaign daily cap: bail if the campaign's own soft limit is reached
 *  3. LRU rotation: pick the active inbox with the oldest last_sent_at
 */
class InboxRotationService
{
    public function __construct(private readonly LoggerInterface $logger) {}

    /**
     * Select and atomically reserve a sending slot for the given lead.
     *
     * @return OutreachEmailAccount|null  null when no inbox has remaining capacity.
     */
    public function selectInbox(OutreachLead $lead, OutreachCampaign $campaign): ?OutreachEmailAccount
    {
        return DB::transaction(function () use ($lead, $campaign) {

            // ── 1. Sticky assignment ─────────────────────────────────────────
            if ($lead->assigned_email_account_id) {
                $account = $this->reserveCapacity($lead->assigned_email_account_id);

                if ($account) {
                    return $account;
                }

                $this->logger->info('[Outreach] Sticky inbox saturated, falling back to rotation', [
                    'lead_id'    => $lead->id,
                    'account_id' => $lead->assigned_email_account_id,
                ]);
            }

            // ── 2. Campaign-level daily cap (soft limit, not row-locked) ─────
            if ($campaign->isOverDailyLimit()) {
                $this->logger->info('[Outreach] Campaign daily limit reached, skipping', [
                    'campaign_id' => $campaign->id,
                ]);
                return null;
            }

            // ── 3. LRU rotation ──────────────────────────────────────────────
            // Find the least-recently-used inbox that still has capacity.
            // Exclude accounts at or above the consecutive-failure threshold —
            // they have been auto-disabled or are degraded and should not receive
            // new sends until an operator investigates.
            // lockForUpdate() here establishes our position in the queue of workers
            // competing for this row — whichever worker gets here first wins.
            $candidate = OutreachEmailAccount::where('is_active', true)
                ->where('consecutive_failures', '<', \App\Outreach\Models\OutreachEmailAccount::FAILURE_THRESHOLD)
                ->whereColumn('sent_today', '<', 'daily_limit')
                ->orderByRaw('COALESCE(last_sent_at, "1970-01-01") ASC')
                ->lockForUpdate()
                ->first();

            if (! $candidate) {
                $this->logger->warning('[Outreach] No inbox has remaining capacity', [
                    'lead_id' => $lead->id,
                ]);
                return null;
            }

            // We already hold the lock via the query above; reserveCapacity will
            // re-acquire it on the same connection (no-op re-lock in InnoDB) and
            // perform the atomic increment.
            $account = $this->reserveCapacity($candidate->id);

            if (! $account) {
                // Shouldn't happen (we hold the lock), but guard defensively
                $this->logger->warning('[Outreach] Candidate account capacity expired unexpectedly', [
                    'lead_id'    => $lead->id,
                    'account_id' => $candidate->id,
                ]);
                return null;
            }

            // Persist sticky assignment so future steps use the same inbox
            $lead->update(['assigned_email_account_id' => $account->id]);

            return $account;
        });
    }

    // ─── Private ────────────────────────────────────────────────────────────

    /**
     * Atomically reserve one daily send slot on the given account.
     *
     * Must be called inside an active DB::transaction(). The method:
     *   1. Acquires an exclusive row lock (SELECT ... FOR UPDATE)
     *   2. Checks capacity against the locked value (no stale read possible)
     *   3. Increments sent_today and sets last_sent_at in the same transaction
     *
     * Returns null if the account is inactive or at its daily limit.
     * Returns the model with in-memory values updated to reflect the write.
     *
     * Callers must NOT call incrementSentToday() after receiving this model.
     */
    private function reserveCapacity(int $accountId): ?OutreachEmailAccount
    {
        $account = OutreachEmailAccount::where('id', $accountId)
            ->where('is_active', true)
            ->where('consecutive_failures', '<', OutreachEmailAccount::FAILURE_THRESHOLD)
            ->lockForUpdate()
            ->first();

        if (! $account || $account->sent_today >= $account->daily_limit) {
            return null;
        }

        // Write the increment while we hold the exclusive lock.
        // No other worker can read or modify this row until the transaction commits.
        DB::table('outreach_email_accounts')
            ->where('id', $account->id)
            ->update([
                'sent_today'   => DB::raw('sent_today + 1'),
                'last_sent_at' => now(),
            ]);

        // Keep the in-memory model consistent so callers can read sent_today
        // without a round-trip.
        $account->sent_today   += 1;
        $account->last_sent_at = now();

        return $account;
    }
}
