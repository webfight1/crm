<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logi arve number (per-arve mudel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merit_reminder_logs', function (Blueprint $table) {
            $table->string('invoice_no')->nullable()->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('merit_reminder_logs', function (Blueprint $table) {
            $table->dropColumn('invoice_no');
        });
    }
};
