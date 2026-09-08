<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-step email attachments.
 *
 * Each entry is stored as JSON: {path, name, mime, size} where `path` is
 * relative to the `local` filesystem disk. Resolved to an absolute path by
 * OutreachCampaignStep::attachmentsForMailer() at send time and passed to
 * OutreachMailer::send()'s $attachments argument (which already supports it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_campaign_steps', function (Blueprint $table) {
            $table->json('attachments')->nullable()->after('body_template');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_campaign_steps', function (Blueprint $table) {
            $table->dropColumn('attachments');
        });
    }
};
