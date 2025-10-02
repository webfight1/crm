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
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();  // Pakkumise number (nt Q2025001)
            $table->foreignId('deal_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();  // Kes koostas
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('vat_rate', 5, 2)->default(20.00);  // KM määr
            $table->decimal('vat_amount', 10, 2);  // KM summa
            $table->decimal('total', 10, 2);  // Kogusumma koos KMga
            $table->string('status')->default('draft');  // draft, sent, accepted, rejected
            $table->date('valid_until')->nullable();  // Pakkumise kehtivusaeg
            $table->text('terms')->nullable();  // Maksetingimused jms
            $table->text('notes')->nullable();  // Lisamärkused
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
