<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-lead MX validation state. Populated by outreach:check-mx and
 * consulted by OutreachEmailService before dispatching a send.
 *
 *   mx_ok         — nullable boolean:
 *                     null  = never checked yet
 *                     true  = domain accepts mail (has MX records)
 *                     false = no MX / domain doesn't exist → skip send
 *   mx_checked_at — when the check ran, so we can re-verify stale entries
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->boolean('mx_ok')->nullable()->after('email');
            $table->timestamp('mx_checked_at')->nullable()->after('mx_ok');
            $table->index('mx_ok');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropIndex(['mx_ok']);
            $table->dropColumn(['mx_ok', 'mx_checked_at']);
        });
    }
};
