<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEO pipeline: the keyword's own landing page + the clarification e-mail.
 *
 *   outreach_leads.serp_url           — ranking URL from the CSV, if the export has one
 *   outreach_leads.seo_stage          — clarify_drafted → awaiting_answer → answered
 *   outreach_leads.seo_clarify_body   — HTML draft pre-filled in the inbox reply form
 *   outreach_leads.seo_extra_keywords — other keywords the client named, one per
 *                                       line as "keyword | landing url" (url may be empty)
 *   seo_audits.page_source            — how the audited page was chosen:
 *                                       csv | found | home | client | manual | none
 *   seo_audits.page_note              — human-readable detail for the above
 *   seo_audit_checks row              — "Märksõnal on oma leht" (builtin)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->string('serp_url', 500)->nullable()->after('serp_competitors');
            $table->string('seo_stage', 20)->nullable()->after('deal_id');
            $table->text('seo_clarify_body')->nullable()->after('seo_stage');
            $table->text('seo_extra_keywords')->nullable()->after('seo_clarify_body');
        });

        Schema::table('seo_audits', function (Blueprint $table) {
            $table->string('page_source', 16)->nullable()->after('keyword');
            $table->string('page_note', 500)->nullable()->after('page_source');
        });

        if (! DB::table('seo_audit_checks')->where('key', 'keyword_landing_page')->exists()) {
            DB::table('seo_audit_checks')->insert([
                'key'                => 'keyword_landing_page',
                'type'               => 'builtin',
                'label'              => 'Märksõnal on oma leht',
                'question'           => null,
                'client_explanation' => 'Google reastab lehti, mitte ettevõtteid. Kui teenusel pole oma lehte, pole Google\'il midagi, mida selle otsingu peale näidata.',
                'enabled'            => true,
                'weight'             => 4,
                'fix_title'          => 'Märksõnale eraldi teenuselehe loomine',
                'fix_price'          => 250,
                'fix_quantity'       => 1,
                'fix_unit'           => 'tk',
                'sort_order'         => 5,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('seo_audit_checks')->where('key', 'keyword_landing_page')->delete();

        Schema::table('seo_audits', function (Blueprint $table) {
            $table->dropColumn(['page_source', 'page_note']);
        });

        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropColumn(['serp_url', 'seo_stage', 'seo_clarify_body', 'seo_extra_keywords']);
        });
    }
};
