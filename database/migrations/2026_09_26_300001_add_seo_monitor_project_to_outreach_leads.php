<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** outreach_leads.seo_monitor_project_id — the client's project in SEO-monitor (seo.webfight.ee). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->unsignedBigInteger('seo_monitor_project_id')->nullable()->after('seo_access_body');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropColumn('seo_monitor_project_id');
        });
    }
};
