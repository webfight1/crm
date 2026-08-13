<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arvete PDF-manuste seaded: kas lisada arved manusena ja mitu maksimaalselt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merit_reminder_settings', function (Blueprint $table) {
            $table->boolean('attach_pdfs')->default(true)->after('from_email');
            $table->unsignedSmallInteger('max_attachments')->default(50)->after('attach_pdfs');
        });
    }

    public function down(): void
    {
        Schema::table('merit_reminder_settings', function (Blueprint $table) {
            $table->dropColumn(['attach_pdfs', 'max_attachments']);
        });
    }
};
