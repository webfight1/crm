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
        Schema::table('deals', function (Blueprint $table) {
            $table->enum('clarity_level', ['clear', 'medium', 'vague'])->nullable()->after('notes');
            $table->enum('revenue_model', ['hourly_partner', 'fixed_project', 'retainer', 'uncertain'])->nullable()->after('clarity_level');
            $table->integer('estimated_hours')->nullable()->after('revenue_model');
            $table->enum('work_type', ['technical', 'design', 'copywriting', 'ecommerce', 'website'])->nullable()->after('estimated_hours');
            $table->enum('risk_level', ['low', 'medium', 'high'])->nullable()->after('work_type');
            $table->boolean('is_fast_cash')->default(false)->after('risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['clarity_level', 'revenue_model', 'estimated_hours', 'work_type', 'risk_level', 'is_fast_cash']);
        });
    }
};
