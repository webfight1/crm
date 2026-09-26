<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra pages of one client: an audit of another page/keyword hangs off the
 * client's main audit, so one deal gets one combined quotation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_audits', function (Blueprint $table) {
            $table->unsignedBigInteger('main_audit_id')->nullable()->after('lead_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('seo_audits', function (Blueprint $table) {
            $table->dropColumn('main_audit_id');
        });
    }
};
