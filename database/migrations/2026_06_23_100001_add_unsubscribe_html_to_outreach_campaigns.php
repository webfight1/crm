<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campaign unsubscribe / opt-out HTML block appended to every
 * cold-campaign email sent from this campaign. Only used on the
 * campaign-send path (OutreachEmailService); inbox replies and
 * quotation emails never touch it — 1-1 conversation mail
 * shouldn't carry a "click here to unsubscribe" line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $table) {
            $table->text('unsubscribe_html')->nullable()->after('ai_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $table) {
            $table->dropColumn('unsubscribe_html');
        });
    }
};
