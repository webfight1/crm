<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manually-curated allowlist of inbound email addresses to surface in the
 * Outreach unified inbox.
 *
 * Why: the existing detection strategies (Message-ID, lead address, CRM
 * Customer/Contact) miss "known correspondents" who aren't outreach leads
 * and aren't yet in the CRM as Customers/Contacts. This table lets the
 * operator manually whitelist such addresses so their inbound mail is
 * pulled into /outreach/inbox like any other thread.
 *
 * Messages from watched-only senders are persisted with all attribution
 * FKs (lead_id / customer_id / contact_id) null; the inbox view groups by
 * LOWER(from_email) so they surface naturally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_watched_emails', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('label', 200)->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->foreignId('created_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_watched_emails');
    }
};
