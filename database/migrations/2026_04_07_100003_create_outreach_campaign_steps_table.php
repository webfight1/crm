<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_campaign_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')
                  ->constrained('outreach_campaigns')
                  ->cascadeOnDelete();

            // Execution order within a campaign (1, 2, 3...)
            $table->unsignedTinyInteger('step_order');

            // Days since lead was enrolled to send this step (0 = immediately, 2 = day 2, 5 = day 5)
            $table->unsignedSmallInteger('day_offset')->default(0);

            $table->string('subject');

            // Supports variables: {{first_name}}, {{last_name}}, {{company}}, {{website}}, {{email}}
            $table->longText('body_template');

            $table->timestamps();

            $table->unique(['campaign_id', 'step_order']);
            $table->index(['campaign_id', 'day_offset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_campaign_steps');
    }
};
