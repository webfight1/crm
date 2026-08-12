<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meriti meeldetuletuste auditlogi — iga saadetud (või ebaõnnestunud) kiri.
 * Kasutatakse ajaloovaates ja korduse vältimiseks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merit_reminder_logs', function (Blueprint $table) {
            $table->id();

            $table->string('merit_customer_id')->index();  // Meriti PartnerId
            $table->string('customer_name')->nullable();
            $table->string('email')->nullable();
            $table->unsignedTinyInteger('level');           // 1..3
            $table->unsignedInteger('overdue_days')->default(0);
            $table->decimal('total_unpaid', 12, 2)->default(0);
            $table->json('invoice_numbers')->nullable();
            $table->string('status')->default('sent');      // sent | failed | skipped
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['merit_customer_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_reminder_logs');
    }
};
