<?php

namespace App\Outreach\Services;

use App\Models\Contact;
use App\Models\Customer;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Outreach\Models\OutreachSendLog;

/**
 * Cross-module bridge between Outreach data and the rest of the CRM
 * (Customer / Contact records).
 *
 * Layer 4 v1: links are derived at read time by matching email addresses.
 * This avoids a schema migration and keeps the link "soft" — if an outreach
 * lead's email later resolves to a Customer that didn't exist when the lead
 * was created, the link appears automatically. A future iteration may add
 * an explicit foreign key on outreach_leads if dedup or promotion workflows
 * require it.
 */
class OutreachActivityLookup
{
    /**
     * Summarise all outreach activity tied to a given email address.
     *
     * Returns an associative array with:
     *   - has_activity:    bool   — true if any leads exist for this email
     *   - lead_count:      int    — number of distinct OutreachLead rows
     *   - reply_count:     int    — inbound messages received
     *   - sent_count:      int    — campaign sends actually delivered
     *   - last_received_at: ?Carbon — timestamp of latest inbound reply
     *   - last_sent_at:    ?Carbon — timestamp of latest delivered send
     *   - campaigns:       Collection<string> — distinct campaign names
     *   - latest_subject:  ?string — subject of the latest inbound reply
     *   - latest_snippet:  ?string — first 200 chars of latest inbound body
     *   - inbox_url:       ?string — direct link to the inbox thread view
     */
    public function summaryForEmail(?string $email): array
    {
        $empty = [
            'has_activity'     => false,
            'lead_count'       => 0,
            'reply_count'      => 0,
            'sent_count'       => 0,
            'last_received_at' => null,
            'last_sent_at'     => null,
            'campaigns'        => collect(),
            'latest_subject'   => null,
            'latest_snippet'   => null,
            'inbox_url'        => null,
        ];

        if ($email === null || trim($email) === '') {
            return $empty;
        }

        $emailLower = strtolower(trim($email));

        $leads = OutreachLead::with('campaign')
            ->whereRaw('LOWER(email) = ?', [$emailLower])
            ->get();

        if ($leads->isEmpty()) {
            return $empty;
        }

        $leadIds = $leads->pluck('id');

        $replyCount = OutreachMessage::whereIn('lead_id', $leadIds)
            ->where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->count();

        $sentCount = OutreachSendLog::whereIn('lead_id', $leadIds)
            ->where('status', OutreachSendLog::STATUS_SENT)
            ->count();

        $latest = OutreachMessage::whereIn('lead_id', $leadIds)
            ->where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->orderByDesc('received_at')
            ->first();

        $lastSentAt = OutreachSendLog::whereIn('lead_id', $leadIds)
            ->where('status', OutreachSendLog::STATUS_SENT)
            ->max('sent_at');

        $snippet = null;
        if ($latest) {
            $raw = $latest->body_text ?: strip_tags((string) $latest->body_html);
            $snippet = trim(preg_replace('/\s+/', ' ', $raw));
            if (mb_strlen($snippet) > 200) {
                $snippet = mb_substr($snippet, 0, 200) . '…';
            }
        }

        $encoded = rtrim(strtr(base64_encode($emailLower), '+/', '-_'), '=');

        return [
            'has_activity'     => true,
            'lead_count'       => $leads->count(),
            'reply_count'      => $replyCount,
            'sent_count'       => $sentCount,
            'last_received_at' => $latest?->received_at,
            'last_sent_at'     => $lastSentAt ? \Carbon\Carbon::parse($lastSentAt) : null,
            'campaigns'        => $leads->pluck('campaign.name')->filter()->unique()->values(),
            'latest_subject'   => $latest?->subject,
            'latest_snippet'   => $snippet,
            'inbox_url'        => route('outreach.inbox.thread', $encoded),
        ];
    }

    /**
     * Reverse lookup: given an email seen in the outreach inbox, find the
     * matching Customer or Contact record (if any). Customer takes precedence;
     * if no Customer matches, fall back to Contact.
     *
     * Returns ['customer' => Customer|null, 'contact' => Contact|null].
     */
    public function findCrmRecord(?string $email): array
    {
        $result = ['customer' => null, 'contact' => null];

        if ($email === null || trim($email) === '') {
            return $result;
        }

        $emailLower = strtolower(trim($email));

        $result['customer'] = Customer::whereRaw('LOWER(email) = ?', [$emailLower])->first();
        $result['contact']  = Contact::whereRaw('LOWER(email) = ?', [$emailLower])->first();

        return $result;
    }
}
