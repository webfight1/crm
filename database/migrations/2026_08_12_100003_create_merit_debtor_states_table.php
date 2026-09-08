<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meriti võlgniku jooksva võlaepisoodi olek — jälgib, mitmes meeldetuletus
 * on juba saadetud. Kui klient enam võlaraportis pole (võlg tasutud), siis
 * highest_level_sent nullitakse ja uus võlg algab taas 1. astmest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merit_debtor_states', function (Blueprint $table) {
            $table->id();
            $table->string('merit_customer_id')->unique();  // Meriti PartnerId
            $table->unsignedTinyInteger('highest_level_sent')->default(0);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('debt_cleared_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_debtor_states');
    }
};
