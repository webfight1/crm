<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // Optional global cap across all inboxes for this campaign
            $table->unsignedSmallInteger('daily_limit')->nullable();

            // Stop sending sequence when lead replies
            $table->boolean('reply_stop_enabled')->default(true);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_campaigns');
    }
};
