<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Millal saadeti käsitsi-helistamise teavitus (kui kliendile on saadetud
 * max arv kirju). Väldib teavituse kordamist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merit_debtor_states', function (Blueprint $table) {
            $table->timestamp('handoff_notified_at')->nullable()->after('debt_cleared_at');
        });
    }

    public function down(): void
    {
        Schema::table('merit_debtor_states', function (Blueprint $table) {
            $table->dropColumn('handoff_notified_at');
        });
    }
};
