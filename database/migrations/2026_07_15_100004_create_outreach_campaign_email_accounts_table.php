<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campaign sending mailboxes (many-to-many).
 *
 * When a campaign has rows here, it may ONLY send from those mailboxes
 * (InboxRotationService restricts the rotation to them). When it has none,
 * behaviour is unchanged — the campaign rotates across every active sending
 * inbox, as before. This lets "campaign A from kristina@, campaign B from
 * marius@" while keeping one shared instance, lead pool and inbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_campaign_email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                  ->constrained('outreach_campaigns')->cascadeOnDelete();
            $table->foreignId('email_account_id')
                  ->constrained('outreach_email_accounts')->cascadeOnDelete();
            $table->unique(['campaign_id', 'email_account_id'], 'campaign_account_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_campaign_email_accounts');
    }
};
