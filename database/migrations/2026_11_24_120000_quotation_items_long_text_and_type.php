<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Long row descriptions (varchar(255) made long rows fail with a DB error) and
 * text-only rows: type 'text' is a heading/explanation line with no price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->text('description')->change();
            $table->string('type', 10)->default('item')->after('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
