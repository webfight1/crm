<?php

namespace App\Outreach\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One physical email message in an outreach conversation.
 *
 * Inbound rows are populated by ReplyDetectionService when a reply is matched
 * to one of our leads (via Message-ID header or sender-address heuristic).
 *
 * Outbound rows are reserved for CRM-originated replies (Layer 2 — not yet
 * implemented). Until then, sent campaign messages live in OutreachSendLog
 * and the inbox UI joins both sources at read-time.
 */
class OutreachMessage extends Model
{
    protected $table = 'outreach_messages';

    const DIRECTION_INBOUND  = 'inbound';
    const DIRECTION_OUTBOUND = 'outbound';

    protected $fillable = [
        'lead_id',
        'customer_id',
        'contact_id',
        'email_account_id',
        'direction',
        'message_id',
        'in_reply_to',
        'references_header',
        'from_email',
        'from_name',
        'subject',
        'body_text',
        'body_html',
        'has_attachments',
        'received_at',
        'read_at',
        'imap_uid',
    ];

    protected $casts = [
        'received_at'     => 'datetime',
        'read_at'         => 'datetime',
        'has_attachments' => 'boolean',
        'imap_uid'        => 'integer',
    ];

    protected static function booted(): void
    {
        // SEO clarification round: our reply to a lead with a clarification
        // draft = the question went out; the lead's next inbound = the answer.
        static::created(function (OutreachMessage $msg) {
            if (! config('app.seo_pipeline')) {
                return;
            }
            // Hand-added SEO clients' replies are matched to the Customer only.
            $lead = $msg->lead_id ? $msg->lead : ($msg->customer_id
                ? OutreachLead::where('customer_id', $msg->customer_id)->whereNotNull('seo_stage')->latest('id')->first()
                : null);
            if (! $lead) {
                return;
            }

            if ($msg->direction === self::DIRECTION_OUTBOUND && $lead->seo_stage === 'clarify_drafted') {
                $lead->update(['seo_stage' => 'awaiting_answer']);
            } elseif ($msg->direction === self::DIRECTION_INBOUND && $lead->seo_stage === 'awaiting_answer'
                && (! $msg->received_at || $msg->received_at->gt(now()->subDays(2)))
            ) {
                \App\Seo\Jobs\HandleSeoClarifyAnswerJob::dispatch($lead->id)->delay(now()->addMinutes(2));
            }
        });

        // Telegram alert for a freshly received reply. The received_at guard
        // keeps IMAP backfills of old mail from flooding the chat.
        static::created(function (OutreachMessage $msg) {
            if ($msg->direction !== self::DIRECTION_INBOUND
                || ($msg->received_at && $msg->received_at->lt(now()->subHours(2)))
            ) {
                return;
            }

            $from    = $msg->from_name ? "{$msg->from_name} <{$msg->from_email}>" : $msg->from_email;
            $mailbox = $msg->emailAccount?->email;
            $context = $msg->lead?->campaign?->name
                ? 'Kampaania: ' . $msg->lead->campaign->name
                : ($msg->customer_id || $msg->contact_id ? 'CRM klient' : null);

            \App\Support\Telegram::send(
                "📩 Uus kiri (" . config('app.name') . ")\n"
                . "Kellelt: {$from}\n"
                . ($mailbox ? "Postkast: {$mailbox}\n" : '')
                . ($context ? "{$context}\n" : '')
                . 'Teema: ' . ($msg->subject ?: '(teemata)') . "\n"
                . self::inboxThreadUrl($msg->from_email)
            );
        });
    }

    /**
     * Deep link to the inbox thread for a sender — same base64url encoding
     * the inbox index view uses for the {emailEncoded} route segment.
     */
    public static function inboxThreadUrl(?string $email): string
    {
        if (! $email) {
            return route('outreach.inbox.index');
        }

        $encoded = rtrim(strtr(base64_encode(strtolower($email)), '+/', '-_'), '=');

        return route('outreach.inbox.thread', $encoded);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(OutreachLead::class, 'lead_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Customer::class, 'customer_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Contact::class, 'contact_id');
    }

    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(OutreachEmailAccount::class, 'email_account_id');
    }
}
