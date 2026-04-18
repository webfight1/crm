<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds performance / qualification fields to outreach_leads.
 *
 *   lcp_mobile        — Largest Contentful Paint for mobile (e.g. "2.5s").
 *                       Rendered in templates as {{lcp}}.
 *   performance_score — Lighthouse-style score (0-100). Rendered as
 *                       {{performance_score}}.
 *   notes             — Free-form operator notes, not rendered in emails.
 *   qualification     — 'lead' (default) or 'skip'. Skipped rows are excluded
 *                       from outreach processing.
 *
 * All four are importable via CSV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->string('lcp_mobile', 50)
                  ->nullable()
                  ->after('industry');

            $table->unsignedTinyInteger('performance_score')
                  ->nullable()
                  ->after('lcp_mobile');

            $table->text('notes')
                  ->nullable()
                  ->after('performance_score');

            $table->string('qualification', 20)
                  ->default('lead')
                  ->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropColumn([
                'lcp_mobile',
                'performance_score',
                'notes',
                'qualification',
            ]);
        });
    }
};
