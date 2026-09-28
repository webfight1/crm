<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-client SEO log: stage changes, deal / quotation status changes, monitor
 * additions and the operator's own notes (the rest of the timeline — audits,
 * e-mails, tasks, time — is read from where it already lives).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->index();
            $table->string('type', 30);
            $table->string('title', 500);
            $table->text('body')->nullable();
            $table->string('url', 500)->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_events');
    }
};
