<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One address can be the contact of several hand-added SEO clients (a person
 * with two companies). Each such client is its own lead, told apart by
 * round_key; campaign imports keep '' so (campaign_id, email) stays unique
 * for them and insertOrIgnore() still skips duplicates.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->string('round_key', 26)->default('')->after('email');
        });
        // New index first: it keeps campaign_id indexed for the foreign key.
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->unique(['campaign_id', 'email', 'round_key'], 'outreach_leads_campaign_email_round_unique');
        });
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropUnique('outreach_leads_campaign_email_unique');
            $table->dropUnique('outreach_leads_campaign_id_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->unique(['campaign_id', 'email'], 'outreach_leads_campaign_email_unique');
            $table->unique(['campaign_id', 'email'], 'outreach_leads_campaign_id_email_unique');
        });
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropUnique('outreach_leads_campaign_email_round_unique');
            $table->dropColumn('round_key');
        });
    }
};
