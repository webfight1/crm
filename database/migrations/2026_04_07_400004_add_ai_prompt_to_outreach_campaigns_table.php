<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds ai_prompt (TEXT, nullable) to outreach_campaigns.
 *
 * When set, this field fully controls the instruction sent to OpenAI for
 * AI personalisation line generation. Operators may include dynamic
 * placeholders that are resolved at send-time using lead data:
 *
 *   {{company}}, {{website}}, {{industry}}, {{first_name}},
 *   {{last_name}}, {{email}}
 *
 * If left null/empty, AiPersonalizationService falls back to its internal
 * default prompt so existing campaigns continue to work without any changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $table) {
            // Placed after 'description' for logical column grouping.
            $table->text('ai_prompt')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $table) {
            $table->dropColumn('ai_prompt');
        });
    }
};
