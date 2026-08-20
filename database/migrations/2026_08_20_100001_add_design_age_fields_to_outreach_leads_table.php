<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds website design-age fields to outreach_leads.
 *
 *   design_year       — Year the site's current design was last established,
 *                       derived from the Wayback Machine (e.g. 2018).
 *                       Rendered in templates as {{design_year}}.
 *   design_age        — Age of the current design in years (currentYear -
 *                       design_year, e.g. 8). Rendered as {{design_age}}.
 *   design_similarity — 0-100 content similarity of the oldest snapshot that
 *                       still counts as "the same design" versus today. Stored
 *                       for transparency / debugging; not rendered in emails.
 *
 * Populated by:  php artisan outreach:measure-design-age {campaign}
 * All three are also importable via CSV (design_year, design_age).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->unsignedSmallInteger('design_year')
                  ->nullable()
                  ->after('performance_score');

            $table->unsignedTinyInteger('design_age')
                  ->nullable()
                  ->after('design_year');

            $table->unsignedTinyInteger('design_similarity')
                  ->nullable()
                  ->after('design_age');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropColumn([
                'design_year',
                'design_age',
                'design_similarity',
            ]);
        });
    }
};
