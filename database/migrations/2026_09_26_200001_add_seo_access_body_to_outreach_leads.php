<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * outreach_leads.seo_access_body — the "please give me access" e-mail draft
 * (hosting-specific SSH instructions + Search Console „Täielik“), pre-filled in
 * the inbox reply form once the deal is won. seo_stage: access_drafted →
 * access_requested.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->text('seo_access_body')->nullable()->after('seo_clarify_body');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropColumn('seo_access_body');
        });
    }
};
