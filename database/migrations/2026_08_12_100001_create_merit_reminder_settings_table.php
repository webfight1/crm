<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meriti võlgnike meeldetuletuste seaded (üherealine konfiguratsioon).
 *
 * Operaator määrab siin, kas automaatika on sees, mitmendal üle-tähtaja
 * päeval iga kuni 3 meeldetuletusest välja läheb, kirjade sisu ja saatja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merit_reminder_settings', function (Blueprint $table) {
            $table->id();

            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('min_overdue_days')->default(1);   // üldine alampiir
            $table->unsignedSmallInteger('min_days_between')->default(5);    // min vahe kahe kirja vahel samale kliendile
            $table->unsignedTinyInteger('send_hour')->default(9);           // ajastatud saatmise tund

            // Kuni 3 meeldetuletuse astet.
            $table->boolean('step1_enabled')->default(true);
            $table->unsignedSmallInteger('step1_days')->default(3);
            $table->string('step1_subject', 500)->nullable();
            $table->text('step1_body')->nullable();

            $table->boolean('step2_enabled')->default(true);
            $table->unsignedSmallInteger('step2_days')->default(10);
            $table->string('step2_subject', 500)->nullable();
            $table->text('step2_body')->nullable();

            $table->boolean('step3_enabled')->default(true);
            $table->unsignedSmallInteger('step3_days')->default(20);
            $table->string('step3_subject', 500)->nullable();
            $table->text('step3_body')->nullable();

            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_reminder_settings');
    }
};
