<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a unique idempotency_key to outreach_send_logs.
 *
 * Purpose:
 *   Prevents duplicate sends when a queue worker dies after the SMTP
 *   transport succeeds but before the log row is marked as 'sent'.
 *
 *   Key format:  l{lead_id}_s{campaign_step_id}
 *   Lifecycle:
 *     - Set to the key when the pending log is created (before SMTP send)
 *     - Kept set on success (status='sent') — blocks any future retry
 *     - Nulled out on failure (status='failed') — releases slot for next attempt
 *
 * Why NULL is safe for the unique index:
 *   MySQL / InnoDB treats each NULL as distinct in a unique index.
 *   Multiple failed rows (all NULL) coexist without violating uniqueness.
 *   Existing rows (all NULL) are unaffected by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_send_logs', function (Blueprint $table) {
            $table->string('idempotency_key', 100)
                  ->nullable()
                  ->unique()
                  ->after('message_id');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_send_logs', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
