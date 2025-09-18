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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['call', 'email', 'meeting', 'follow_up', 'other'])->default('other');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->datetime('due_date')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('deal_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // assigned to user
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
