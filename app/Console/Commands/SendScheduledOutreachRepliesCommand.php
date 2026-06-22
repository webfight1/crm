<?php

namespace App\Console\Commands;

use App\Outreach\Models\OutreachMessage;
use App\Outreach\Models\OutreachScheduledReply;
use App\Outreach\Services\OutreachMailer;
use Illuminate\Console\Command;
use Throwable;

/**
 * Picks scheduled inbox replies whose scheduled_at has passed and
 * dispatches them via OutreachMailer. Persists an outbound row in
 * outreach_messages on success so the thread view picks the reply up
 * exactly the same as an immediately-sent one.
 *
 * Failures flip status to 'failed' with the exception message stored;
 * they don't auto-retry — the operator can re-schedule from the UI.
 */
class SendScheduledOutreachRepliesCommand extends Command
{
    protected $signature   = 'outreach:send-scheduled-replies';
    protected $description = 'Dispatch outbox replies that the operator scheduled for a specific time';

    public function handle(OutreachMailer $mailer): int
    {
        $due = OutreachScheduledReply::with('account')
            ->where('status', OutreachScheduledReply::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        if ($due->isEmpty()) {
            return self::SUCCESS;
        }

        $this->info("Found {$due->count()} due reply/replies");

        foreach ($due as $row) {
            if (! $row->account || ! $row->account->is_active) {
                $row->update([
                    'status'        => OutreachScheduledReply::STATUS_FAILED,
                    'error_message' => 'Saatja konto pole aktiivne või on kustutatud.',
                ]);
                continue;
            }

            try {
                $messageId = $mailer->send(
                    account:    $row->account,
                    toEmail:    $row->to_email,
                    toName:     $row->to_name ?: $row->to_email,
                    subject:    $row->subject,
                    htmlBody:   nl2br(e($row->body)),
                    inReplyTo:  $row->in_reply_to,
                    references: $row->references_header,
                );

                // Mirror the live reply path: persist an outbound message so
                // the inbox thread view shows it. Attribution stays null
                // (watched-only threads); the In-Reply-To bridge in
                // inboxThread picks it up regardless.
                OutreachMessage::create([
                    'email_account_id'  => $row->account_id,
                    'direction'         => OutreachMessage::DIRECTION_OUTBOUND,
                    'message_id'        => $messageId,
                    'in_reply_to'       => $row->in_reply_to,
                    'references_header' => $row->references_header,
                    'from_email'        => $row->account->email,
                    'from_name'         => $row->account->name,
                    'subject'           => $row->subject,
                    'body_text'         => $row->body,
                    'body_html'         => null,
                    'has_attachments'   => false,
                    'received_at'       => now(),
                ]);

                $row->update([
                    'status'  => OutreachScheduledReply::STATUS_SENT,
                    'sent_at' => now(),
                ]);

                $this->info(" sent #{$row->id} → {$row->to_email}");
            } catch (Throwable $e) {
                $row->update([
                    'status'        => OutreachScheduledReply::STATUS_FAILED,
                    'error_message' => $e->getMessage(),
                ]);
                $this->error(" failed #{$row->id}: " . $e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
