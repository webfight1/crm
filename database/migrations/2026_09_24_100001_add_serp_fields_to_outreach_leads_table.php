<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Google ranking (SERP) fields to outreach_leads, for "you're not on
 * page 1" campaigns.
 *
 *   serp_keyword     — search phrase checked, e.g. "elektritööd tartus".
 *                      Rendered as {{keyword}}.
 *   serp_position    — organic position for that phrase (e.g. 11).
 *                      Rendered as {{position}}.
 *   serp_page        — Google results page the position falls on (e.g. 2).
 *                      Rendered as {{google_page}}.
 *   serp_competitors — domains ranking on page 1, comma-separated.
 *                      Rendered as {{competitors}}.
 *
 * All four are importable via CSV (keyword, position, google_page, competitors).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->string('serp_keyword')->nullable()->after('design_similarity');
            $table->unsignedSmallInteger('serp_position')->nullable()->after('serp_keyword');
            $table->unsignedTinyInteger('serp_page')->nullable()->after('serp_position');
            $table->string('serp_competitors')->nullable()->after('serp_page');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropColumn([
                'serp_keyword',
                'serp_position',
                'serp_page',
                'serp_competitors',
            ]);
        });
    }
};
