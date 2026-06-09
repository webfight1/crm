<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log of every quotation-email send attempt — one row per click of
 * "Saada e-postiga", regardless of whether the actual delivery succeeded.
 *
 * Surfaced on the quotation show page as a "Saatmise logi" panel so the
 * operator can see exactly when each copy went out, to which address,
 * via which mailbox, and whether anything failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_account_id')
                  ->nullable()
                  ->constrained('outreach_email_accounts')
                  ->nullOnDelete();
            $table->string('to_email');
            $table->string('subject', 500);
            $table->string('status', 16);          // 'sent' | 'failed'
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['quotation_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_email_sends');
    }
};
