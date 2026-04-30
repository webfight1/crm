<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-email-address archive flag for the outreach inbox.
 *
 * Why a separate table rather than a column on outreach_messages: an inbox
 * "thread" is identified by from_email, but messages with that email span
 * many rows (multiple campaigns, multiple replies). One row per archived
 * email keeps the archived state cheap to read and avoids touching every
 * message row when the operator clicks "archive".
 *
 * Auto-unarchive: when ReplyDetectionService persists a new inbound message
 * for an archived email, it deletes the archived row so the thread re-enters
 * the regular inbox. Mirrors Gmail's "archive resurfaces on reply" behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_archived_threads', function (Blueprint $table) {
            $table->id();
            $table->string('email_lower')->unique();
            $table->timestamp('archived_at');
            $table->foreignId('archived_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_archived_threads');
    }
};
