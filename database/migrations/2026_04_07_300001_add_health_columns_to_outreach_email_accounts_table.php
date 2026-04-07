<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds inbox health-tracking columns to outreach_email_accounts.
 *
 * consecutive_failures  — incremented on each SMTP/IMAP failure; reset on success.
 *                         When this reaches the threshold (default 5), the inbox
 *                         is automatically disabled (is_active = false).
 *
 * last_error            — human-readable description of the most recent failure.
 *                         Stored for operator visibility; not used in logic.
 *
 * disabled_at           — timestamp set when the inbox is auto-disabled.
 *                         Null means the inbox was either never disabled or was
 *                         manually re-enabled. Used to distinguish auto-disable
 *                         from manual disable in admin UIs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->unsignedSmallInteger('consecutive_failures')
                  ->default(0)
                  ->after('last_sent_at');

            $table->text('last_error')
                  ->nullable()
                  ->after('consecutive_failures');

            $table->timestamp('disabled_at')
                  ->nullable()
                  ->after('last_error');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->dropColumn(['consecutive_failures', 'last_error', 'disabled_at']);
        });
    }
};
