<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a unique constraint on (campaign_id, email) to outreach_leads.
 *
 * Purpose: prevent race-condition duplicates during concurrent CSV imports.
 * The CSV import service uses insertOrIgnore() which relies on this index to
 * silently skip conflicting rows rather than throwing.
 *
 * NOTE: If existing data already contains duplicate (campaign_id, email) pairs
 * this migration will fail. Clean up duplicates first with:
 *   DELETE l1 FROM outreach_leads l1
 *   INNER JOIN outreach_leads l2
 *   ON l1.campaign_id = l2.campaign_id AND l1.email = l2.email AND l1.id > l2.id;
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->unique(['campaign_id', 'email'], 'outreach_leads_campaign_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropUnique('outreach_leads_campaign_email_unique');
        });
    }
};
