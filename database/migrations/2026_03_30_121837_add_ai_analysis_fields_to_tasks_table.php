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
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('work_type', ['technical', 'design', 'copywriting', 'marketing', 'ecommerce', 'website', 'project', 'maintenance', 'other'])->nullable()->after('notes');
            $table->enum('clarity_level', ['clear', 'medium', 'vague'])->nullable()->after('work_type');
            $table->enum('revenue_model', ['hourly_partner', 'fixed_project', 'retainer', 'internal', 'uncertain'])->nullable()->after('clarity_level');
            $table->enum('cashflow_speed', ['fast', 'medium', 'slow'])->nullable()->after('revenue_model');
            $table->enum('risk_level', ['low', 'medium', 'high'])->nullable()->after('cashflow_speed');
            $table->integer('estimated_hours')->nullable()->after('risk_level');
            $table->unsignedTinyInteger('value_score')->nullable()->after('estimated_hours');
            $table->unsignedTinyInteger('cashflow_score')->nullable()->after('value_score');
            $table->boolean('is_quick_win')->default(false)->after('cashflow_score');
            $table->boolean('is_blocking')->default(false)->after('is_quick_win');
            $table->string('recommended_next_step')->nullable()->after('is_blocking');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn([
                'work_type',
                'clarity_level',
                'revenue_model',
                'cashflow_speed',
                'risk_level',
                'estimated_hours',
                'value_score',
                'cashflow_score',
                'is_quick_win',
                'is_blocking',
                'recommended_next_step'
            ]);
        });
    }
};
