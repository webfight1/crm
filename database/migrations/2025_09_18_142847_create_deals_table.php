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
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('value', 15, 2);
            $table->enum('stage', ['lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost'])->default('lead');
            $table->integer('probability')->default(0); // 0-100%
            $table->date('expected_close_date')->nullable();
            $table->date('actual_close_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // assigned to user
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
