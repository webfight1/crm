<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holds inbox replies that the operator authored but asked to send
 * at a specific future time (e.g. composed at 23:00 but scheduled
 * for 09:00 the next morning, to avoid night-time delivery).
 *
 * A scheduler command (outreach:send-scheduled-replies) runs every
 * minute, picks rows with status='pending' AND scheduled_at <= now(),
 * dispatches them via OutreachMailer, and flips status to 'sent' or
 * 'failed'. The status column doubles as the cancellation gate —
 * cancelled rows simply stay in the table for audit but never fire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_scheduled_replies', function (Blueprint $table) {
            $table->id();

            // Thread identity: address whose inbox-thread the reply is for.
            $table->string('email_lower');

            // Send target — usually same as email_lower but allows the
            // operator to send to a different address than the thread's.
            $table->string('to_email');
            $table->string('to_name')->nullable();

            $table->string('subject', 500);
            $table->longText('body');

            // Threading headers, prebuilt at compose time.
            $table->string('in_reply_to')->nullable();
            $table->text('references_header')->nullable();

            // Sender mailbox — captured at compose time so a later
            // account swap doesn't change who appears in the From: line.
            $table->foreignId('account_id')
                  ->constrained('outreach_email_accounts')
                  ->cascadeOnDelete();

            $table->timestamp('scheduled_at');
            $table->string('status', 16)->default('pending'); // pending|sent|failed|cancelled
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();

            $table->foreignId('created_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['email_lower', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_scheduled_replies');
    }
};
