<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, modify the type enum
        DB::statement("ALTER TABLE tasks MODIFY COLUMN type ENUM(
            'call',
            'email',
            'meeting',
            'follow_up',
            'development',
            'bug_fix',
            'content_creation',
            'proposal_creation',
            'testing',
            'other'
        ) DEFAULT 'other'");

        // Then, modify the status enum
        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM(
            'pending',
            'in_progress',
            'needs_testing',
            'needs_clarification',
            'completed',
            'cancelled'
        ) DEFAULT 'pending'");

        // Update existing records with old statuses
        DB::statement("UPDATE tasks SET status = 'in_progress' WHERE status = 'pending'");
        DB::statement("UPDATE tasks SET status = 'completed' WHERE status = 'completed'");
        DB::statement("UPDATE tasks SET status = 'cancelled' WHERE status = 'cancelled'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status changes first
        DB::statement("UPDATE tasks SET status = 'pending' WHERE status IN ('in_progress', 'needs_testing', 'needs_clarification')");

        // Then revert the columns to original enums
        DB::statement("ALTER TABLE tasks MODIFY COLUMN type ENUM(
            'call',
            'email',
            'meeting',
            'follow_up',
            'other'
        ) DEFAULT 'other'");

        DB::statement("ALTER TABLE tasks MODIFY COLUMN status ENUM(
            'pending',
            'in_progress',
            'completed',
            'cancelled'
        ) DEFAULT 'pending'");
    }
};
