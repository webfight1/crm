<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Testrežiim: kui täidetud, saadetakse KÕIK meeldetuletused sellele aadressile
 * (mitte päris klientidele) ilma olekut/logisid muutmata. Ohutuks testimiseks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merit_reminder_settings', function (Blueprint $table) {
            $table->string('test_recipient')->nullable()->after('max_attachments');
        });
    }

    public function down(): void
    {
        Schema::table('merit_reminder_settings', function (Blueprint $table) {
            $table->dropColumn('test_recipient');
        });
    }
};
