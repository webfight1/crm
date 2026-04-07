<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_send_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')
                  ->constrained('outreach_leads')
                  ->cascadeOnDelete();
            $table->foreignId('campaign_id')
                  ->constrained('outreach_campaigns')
                  ->cascadeOnDelete();
            $table->foreignId('email_account_id')
                  ->constrained('outreach_email_accounts')
                  ->cascadeOnDelete();
            $table->foreignId('campaign_step_id')
                  ->constrained('outreach_campaign_steps')
                  ->cascadeOnDelete();

            $table->unsignedTinyInteger('step_order');

            $table->string('to_email');
            $table->string('from_email');
            $table->string('subject');
            $table->longText('body');

            // pending | sent | failed | skipped
            $table->string('status')->default('pending');
            $table->text('error_message')->nullable();

            // SMTP Message-ID header value — used for reply thread matching
            $table->string('message_id')->nullable()->index();

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->index(['campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_send_logs');
    }
};
