<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-campaign extra guidance appended to the AI draft generator's
 * base system prompt. Lets the operator inject campaign-specific
 * language (industry-adapted wording, custom design_year phrasing,
 * different offer emphasis) without touching the app code.
 *
 * The base prompt still owns JSON schema, structural rules, and
 * don't-hallucinate guardrails so this field can't accidentally
 * break the output contract.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $table) {
            $table->text('draft_prompt_extra')->nullable()->after('ai_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_campaigns', function (Blueprint $table) {
            $table->dropColumn('draft_prompt_extra');
        });
    }
};
