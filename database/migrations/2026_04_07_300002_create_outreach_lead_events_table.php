<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for outreach lead lifecycle events.
 *
 * Every meaningful state change is appended as an immutable row.
 * Records are never updated or deleted — they form the permanent history.
 *
 * event_type values (see OutreachLeadEvent::TYPE_* constants):
 *   enrolled           — lead was added to a campaign
 *   step_sent          — an email step was physically delivered
 *   step_failed        — a send attempt failed (transient or permanent)
 *   step_skipped       — lead bypassed (replied, paused, etc.)
 *   reply_detected     — inbound reply matched this lead
 *   bounced            — hard bounce detected (SMTP 5xx or NDR)
 *   status_changed     — manual or automatic status transition
 *   sequence_completed — all campaign steps exhausted
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_lead_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')
                  ->constrained('outreach_leads')
                  ->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->json('metadata')->nullable();   // step_order, error, message_id, etc.
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — events are immutable

            $table->index('lead_id');
            $table->index(['lead_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_lead_events');
    }
};
