<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('subject');
            $table->foreignId('email_campaign_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('status', ['sent', 'failed', 'bounced'])->default('sent');
            $table->text('response')->nullable(); // API response
            $table->timestamp('sent_at');
            $table->timestamps();
            
            // Index for quick lookups
            $table->index(['email', 'sent_at']);
            $table->index(['user_id', 'email', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
