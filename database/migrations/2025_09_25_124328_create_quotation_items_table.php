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
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->onDelete('cascade');
            $table->string('description');  // Toote/teenuse kirjeldus
            $table->decimal('quantity', 10, 2);  // Kogus
            $table->string('unit')->default('tk');  // Ühik (tk, h, päev jne)
            $table->decimal('unit_price', 10, 2);  // Ühikhind
            $table->decimal('subtotal', 10, 2);  // Summa ilma KMta
            $table->integer('sort_order')->default(0);  // Järjekorra number
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
