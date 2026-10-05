<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal chat monitoring (WhatsApp now, Messenger later) — read-only.
 *
 * chat_threads holds one row per conversation we have seen. Message content
 * is stored only for monitored threads (is_monitored); for the rest we keep
 * just name / phone / last activity so a thread can be switched on later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_threads', function (Blueprint $table) {
            $table->id();
            $table->string('network', 20);                // whatsapp | messenger
            $table->string('external_id');                // chat JID / thread id
            $table->string('phone', 32)->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_group')->default(false);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_monitored')->default(false);
            $table->boolean('auto_ai')->default(false);   // AI triage + Telegram on new messages
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('triaged_at')->nullable();
            $table->json('ai_triage')->nullable();        // last AI suggestion
            $table->timestamps();

            $table->unique(['network', 'external_id']);
            $table->index(['is_monitored', 'last_message_at']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_thread_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('direction', 3);               // in | out
            $table->string('sender_name')->nullable();
            $table->string('type', 20)->default('text');  // text | image | audio | video | document | sticker | other
            $table->text('body')->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('read_at')->nullable();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->unique(['chat_thread_id', 'external_id']);
            $table->index(['chat_thread_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_threads');
    }
};
