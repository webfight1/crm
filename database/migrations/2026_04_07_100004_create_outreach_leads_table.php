<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                  ->constrained('outreach_campaigns')
                  ->cascadeOnDelete();

            // The inbox currently assigned to this lead (sticky once assigned)
            $table->foreignId('assigned_email_account_id')
                  ->nullable()
                  ->constrained('outreach_email_accounts')
                  ->nullOnDelete();

            // Lead data
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email');
            $table->string('company')->nullable();
            $table->string('website')->nullable();

            // Sequence state
            // active | paused | completed | bounced | unsubscribed
            $table->string('status')->default('active');
            $table->unsignedTinyInteger('current_step')->default(0); // 0 = no step sent yet

            // When the lead was added to the campaign sequence
            $table->timestamp('enrolled_at')->useCurrent();

            // When to attempt the next send
            $table->timestamp('next_send_at')->nullable()->index();

            // When the last email was actually sent
            $table->timestamp('last_sent_at')->nullable();

            // Reply detection
            $table->boolean('replied')->default(false);
            $table->timestamp('replied_at')->nullable();

            // Prevents double-dispatch: set when a job is dispatched, cleared after send
            $table->timestamp('processing_since')->nullable();

            $table->timestamps();

            // Primary scheduler query index
            $table->index(['status', 'replied', 'next_send_at']);

            // Unique email per campaign
            $table->unique(['campaign_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_leads');
    }
};
