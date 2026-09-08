<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uus meeldetuletuste rütm: 1. kiri N päeva üle tähtaja, edasi iga X päeva
 * tagant, kokku kuni Y kirja; seejärel teavitus käsitsi helistamiseks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merit_reminder_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('first_reminder_days')->default(7)->after('min_overdue_days');
            $table->unsignedSmallInteger('repeat_interval_days')->default(2)->after('first_reminder_days');
            $table->unsignedSmallInteger('max_reminders')->default(10)->after('repeat_interval_days');
            $table->string('handoff_recipient')->nullable()->after('max_reminders');
        });
    }

    public function down(): void
    {
        Schema::table('merit_reminder_settings', function (Blueprint $table) {
            $table->dropColumn(['first_reminder_days', 'repeat_interval_days', 'max_reminders', 'handoff_recipient']);
        });
    }
};
