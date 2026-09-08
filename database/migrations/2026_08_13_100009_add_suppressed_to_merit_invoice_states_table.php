<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Käsitsi peatamine: kui arve on tegelikult tasutud, aga Merit näitab veel
 * võlga (nt pangaväljavõte ei sidunud), saab operaator arve meeldetuletused
 * käsitsi peatada. Ei puuduta Meriti raamatupidamist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merit_invoice_states', function (Blueprint $table) {
            $table->timestamp('suppressed_at')->nullable()->after('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::table('merit_invoice_states', function (Blueprint $table) {
            $table->dropColumn('suppressed_at');
        });
    }
};
