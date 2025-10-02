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
        // Remove user_id from companies table
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        // Remove user_id from contacts table
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        // Remove user_id from customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back user_id to companies table
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
        });

        // Add back user_id to contacts table
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
        });

        // Add back user_id to customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
        });
    }
};
