<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds read tracking to inbox messages. Inbound messages start as NULL
 * (= unread). The thread view marks them read on first display.
 *
 * Outbound messages don't carry meaningful read state — they're authored
 * locally — but we keep the same column rather than branching the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->timestamp('read_at')->nullable()->after('received_at');
            $table->index(['direction', 'read_at'], 'outreach_messages_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table) {
            $table->dropIndex('outreach_messages_unread_idx');
            $table->dropColumn('read_at');
        });
    }
};
