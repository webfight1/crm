<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds use_ai_line to outreach_campaigns.
 *
 * Default false — existing campaigns are unaffected.
 * When true, the engine generates (or reuses) a one-sentence AI
 * personalisation line for each lead before rendering the email template.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $table) {
            $table->boolean('use_ai_line')
                  ->default(false)
                  ->after('reply_stop_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $table) {
            $table->dropColumn('use_ai_line');
        });
    }
};
