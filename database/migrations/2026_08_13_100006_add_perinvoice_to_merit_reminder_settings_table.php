<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-arve mudel: 4 astet kindlate päevadega (0/2/9/12), 4. mall, teavituse
 * aste (Marius), millisest astmest PDF kaasa, ja arve väljastaja nimi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merit_reminder_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('step4_days')->default(12)->after('step3_body');
            $table->string('step4_subject', 500)->nullable()->after('step4_days');
            $table->text('step4_body')->nullable()->after('step4_subject');

            $table->unsignedTinyInteger('notify_step')->default(3)->after('step4_body');   // mis astmes Mariusele teade
            $table->unsignedTinyInteger('attach_from_step')->default(2)->after('notify_step'); // alates mis astmest PDF
            $table->string('company_name')->nullable()->after('attach_from_step');          // arve väljastaja (nt Kind Studio OÜ)
        });
    }

    public function down(): void
    {
        Schema::table('merit_reminder_settings', function (Blueprint $table) {
            $table->dropColumn(['step4_days', 'step4_subject', 'step4_body', 'notify_step', 'attach_from_step', 'company_name']);
        });
    }
};
