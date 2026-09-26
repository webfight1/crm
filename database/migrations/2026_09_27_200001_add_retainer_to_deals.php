<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monthly fee of a „Püsiklient“ deal: amount, first month, how many months
 * (null = until stopped) and the day of the month the invoice reminder comes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->decimal('retainer_amount', 10, 2)->nullable()->after('revenue_model');
            $table->string('retainer_note')->nullable()->after('retainer_amount');
            $table->date('retainer_start')->nullable()->after('retainer_note');
            $table->unsignedSmallInteger('retainer_months')->nullable()->after('retainer_start');
            $table->unsignedTinyInteger('retainer_day')->default(1)->after('retainer_months');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn(['retainer_amount', 'retainer_note', 'retainer_start', 'retainer_months', 'retainer_day']);
        });
    }
};
