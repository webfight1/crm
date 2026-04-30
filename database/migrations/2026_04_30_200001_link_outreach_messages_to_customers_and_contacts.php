<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allows outreach_messages to attribute conversations to records other than
 * an OutreachLead. Same email address can route to a Customer or Contact
 * record (built in the main CRM tables) instead of, or in addition to, a lead.
 *
 * Why:
 *   The "always-listening" inbox extension catches replies from anyone we
 *   already know — leads, customers, contacts. A long-standing customer who
 *   was never an outreach lead can now have their inbound mail captured
 *   because we link the message to the customer record.
 *
 * Constraint:
 *   At INSERT time at least one of (lead_id, customer_id, contact_id) must
 *   be set. Enforced at the application layer (ReplyDetectionService /
 *   OutreachController). FK ON DELETE = SET NULL keeps message history
 *   intact even if the linked record is later removed — the from_email and
 *   body still tell the story.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table) {
            // 1) Drop the existing strict FK on lead_id so we can null it out.
            $table->dropForeign(['lead_id']);
        });

        Schema::table('outreach_messages', function (Blueprint $table) {
            // 2) Re-define lead_id as nullable + recreate FK with SET NULL.
            $table->foreignId('lead_id')
                  ->nullable()
                  ->change();
            $table->foreign('lead_id')
                  ->references('id')->on('outreach_leads')
                  ->nullOnDelete();

            // 3) New optional links to CRM-side records.
            $table->foreignId('customer_id')
                  ->nullable()
                  ->after('lead_id')
                  ->constrained('customers')
                  ->nullOnDelete();

            $table->foreignId('contact_id')
                  ->nullable()
                  ->after('customer_id')
                  ->constrained('contacts')
                  ->nullOnDelete();
        });

        // Helpful indexes for the inbox-grouping queries.
        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->index(['customer_id', 'received_at'], 'outreach_messages_customer_received_idx');
            $table->index(['contact_id',  'received_at'], 'outreach_messages_contact_received_idx');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->dropIndex('outreach_messages_customer_received_idx');
            $table->dropIndex('outreach_messages_contact_received_idx');
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['contact_id']);
            $table->dropColumn(['customer_id', 'contact_id']);
        });

        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
        });

        // Best-effort restore: rows with NULL lead_id will block the NOT NULL
        // alter; clean those before rolling back if you really need it.
        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable(false)->change();
            $table->foreign('lead_id')
                  ->references('id')->on('outreach_leads')
                  ->cascadeOnDelete();
        });
    }
};
