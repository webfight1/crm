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
        DB::statement("ALTER TABLE deals MODIFY COLUMN stage ENUM('lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost', 'töös', 'tühistatud', 'valmis', 'arveldatud') DEFAULT 'lead'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE deals MODIFY COLUMN stage ENUM('lead', 'qualified', 'proposal', 'negotiation', 'closed_won', 'closed_lost') DEFAULT 'lead'");
    }
};
