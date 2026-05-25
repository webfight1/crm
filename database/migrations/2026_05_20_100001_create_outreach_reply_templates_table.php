<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved reply snippets for the outreach inbox.
 *
 * Operator can pre-author short responses ("vabandus", "küsi rohkem
 * infot", "jah, planeerime kohtumise") and pick one from a dropdown
 * above the reply form — selecting a template fills both subject
 * (optional) and body. Subject from the template overrides only if
 * the user hasn't already typed one; body is replaced entirely.
 *
 * Per-user: each operator manages their own list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_reply_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                       // shown in the dropdown
            $table->string('subject', 500)->nullable();   // optional subject override
            $table->text('body');                         // required body
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_reply_templates');
    }
};
