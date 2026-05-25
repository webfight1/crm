<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HTML signature appended to every outbound email sent through this
 * account. Applied by OutreachMailer at send time, so both cold-campaign
 * sends and CRM replies pick it up without callers needing to remember.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->text('signature_html')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->dropColumn('signature_html');
        });
    }
};
