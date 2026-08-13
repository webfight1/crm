<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Käsitsi lisatud e-postid Meriti klientidele (kellel Meritis e-post puudub).
 * Kasutatakse ainult siis, kui Meritist e-posti ei tule — Meriti e-post võidab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merit_customer_emails', function (Blueprint $table) {
            $table->id();
            $table->string('merit_customer_id')->unique(); // Meriti PartnerId
            $table->string('customer_name')->nullable();
            $table->string('email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merit_customer_emails');
    }
};
