<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-lead AI-generated cold-email draft fields. Populated by
 * OutreachDraftGeneratorService (which fetches the website via
 * WebsiteContextService and calls OpenAI). The operator reviews
 * drafts in the UI, edits if needed, then approves for sending;
 * OutreachEmailService prefers the approved outreach_email_body
 * over the campaign step's body_template.
 *
 * Status lifecycle:
 *   null              → never generated
 *   pending           → generation running / queued
 *   ready             → draft generated, awaiting operator review
 *   approved          → operator approved, safe to send
 *   failed            → generation errored; see outreach_generation_error
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->string('outreach_subject_1', 500)->nullable()->after('design_similarity');
            $table->string('outreach_subject_2', 500)->nullable()->after('outreach_subject_1');
            $table->string('outreach_subject_3', 500)->nullable()->after('outreach_subject_2');
            // Which subject (1-3) the operator picked; defaults to 1.
            $table->unsignedTinyInteger('outreach_selected_subject')->default(1)->after('outreach_subject_3');

            $table->text('website_context_summary')->nullable()->after('outreach_selected_subject');
            $table->text('public_reference_context')->nullable()->after('website_context_summary');
            $table->text('seo_observation')->nullable()->after('public_reference_context');

            $table->longText('outreach_email_body')->nullable()->after('seo_observation');
            $table->longText('outreach_followup_body')->nullable()->after('outreach_email_body');

            $table->string('outreach_generation_status', 32)->nullable()->after('outreach_followup_body');
            $table->text('outreach_generation_error')->nullable()->after('outreach_generation_status');
            $table->json('outreach_sources')->nullable()->after('outreach_generation_error');
            $table->timestamp('outreach_generated_at')->nullable()->after('outreach_sources');

            $table->index('outreach_generation_status');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropIndex(['outreach_generation_status']);
            $table->dropColumn([
                'outreach_subject_1', 'outreach_subject_2', 'outreach_subject_3',
                'outreach_selected_subject',
                'website_context_summary', 'public_reference_context', 'seo_observation',
                'outreach_email_body', 'outreach_followup_body',
                'outreach_generation_status', 'outreach_generation_error',
                'outreach_sources', 'outreach_generated_at',
            ]);
        });
    }
};
