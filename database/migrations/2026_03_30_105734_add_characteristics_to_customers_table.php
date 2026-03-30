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
        Schema::table('customers', function (Blueprint $table) {
            $table->enum('payment_behavior', ['fast', 'normal', 'slow', 'risky'])->default('normal')->after('client_attribute');
            $table->enum('clarity_level', ['clear', 'medium', 'vague'])->nullable()->after('payment_behavior');
            $table->enum('cooperation_level', ['easy', 'normal', 'difficult'])->nullable()->after('clarity_level');
            $table->enum('value_level', ['high', 'medium', 'low'])->nullable()->after('cooperation_level');
            $table->enum('revenue_type', ['hourly_partner', 'project', 'retainer', 'one_time'])->nullable()->after('value_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['payment_behavior', 'clarity_level', 'cooperation_level', 'value_level', 'revenue_type']);
        });
    }
};
