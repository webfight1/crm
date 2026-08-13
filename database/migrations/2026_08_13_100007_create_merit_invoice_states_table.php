<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-arve meeldetuletuste olek — mitmes aste on saadetud, millal, kas Marius
 * teavitatud. Kui arve tasutakse (kaob võlaraportist), märgitakse lahenduks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merit_invoice_states', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_key')->unique();     // customerId|invoiceNo
            $table->string('merit_customer_id')->index();
            $table->string('invoice_no');
            $table->unsignedTinyInteger('highest_step_sent')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('marius_notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_invoice_states');
    }
};
