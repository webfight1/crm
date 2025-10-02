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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');  // Firma nimi
            $table->string('registration_number')->nullable();  // Registrikood
            $table->string('vat_number')->nullable();  // KM number
            $table->string('address')->nullable();  // Aadress
            $table->string('phone')->nullable();  // Telefon
            $table->string('email')->nullable();  // E-post
            $table->string('website')->nullable();  // Veebileht
            $table->string('bank_name')->nullable();  // Panga nimi
            $table->string('bank_account')->nullable();  // Pangakonto
            $table->string('swift')->nullable();  // SWIFT/BIC
            $table->text('quotation_terms')->nullable();  // Vaikimisi pakkumise tingimused
            $table->decimal('default_vat_rate', 5, 2)->default(20.00);  // Vaikimisi KM määr
            $table->string('logo_path')->nullable();  // Logo faili asukoht
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
