<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lead_id')
                  ->constrained('outreach_leads')
                  ->cascadeOnDelete();

            $table->foreignId('email_account_id')
                  ->constrained('outreach_email_accounts')
                  ->cascadeOnDelete();

            // 'inbound' for replies pulled from IMAP. 'outbound' is reserved for
            // CRM-originated replies (Layer 2, not yet implemented).
            $table->string('direction', 16)->default('inbound');

            // RFC 2822 thread identifiers.
            $table->string('message_id')->nullable()->index();
            $table->string('in_reply_to')->nullable();
            $table->text('references_header')->nullable();

            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject', 998)->nullable();

            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();

            $table->boolean('has_attachments')->default(false);

            $table->timestamp('received_at');

            // IMAP UID is stable per-mailbox. Combined with email_account_id it
            // uniquely identifies a physical message and prevents the same reply
            // being persisted twice across poller runs.
            $table->unsignedBigInteger('imap_uid')->nullable();

            $table->timestamps();

            $table->unique(['email_account_id', 'imap_uid'], 'outreach_messages_account_uid_unique');
            $table->index(['lead_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_messages');
    }
};
