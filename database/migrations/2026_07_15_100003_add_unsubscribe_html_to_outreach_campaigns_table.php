<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campaign unsubscribe / opt-out line ("loobumisrida").
 *
 * HTML shown at the very bottom of every cold-send email — AFTER the account
 * signature — via OutreachMailer::send()'s $footer argument. Mirrors the same
 * field the main CRM already uses; the hasColumn guard keeps this idempotent
 * if the main-CRM migration ever lands on the same database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('outreach_campaigns', 'unsubscribe_html')) {
            return;
        }

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
