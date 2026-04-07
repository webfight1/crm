<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds ai_line and industry to outreach_leads.
 *
 * ai_line   — generated once and stored so the same line is reused on every
 *             subsequent step send, avoiding repeated OpenAI API calls.
 *             Null means not yet generated (or campaign does not use AI lines).
 *
 * industry  — optional lead attribute used as context for the AI prompt and
 *             available in email templates as {{industry}}.
 *             Also importable via CSV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->text('ai_line')
                  ->nullable()
                  ->after('website');

            $table->string('industry', 200)
                  ->nullable()
                  ->after('ai_line');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropColumn(['ai_line', 'industry']);
        });
    }
};
