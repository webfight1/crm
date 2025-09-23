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
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->string('csv_id')->nullable()->after('company_name');
            $table->string('csv_company_id')->nullable()->after('csv_id');
            $table->string('sector')->nullable()->after('csv_company_id');
            $table->string('emtak')->nullable()->after('sector');
            $table->string('phone')->nullable()->after('emtak');
            $table->string('website')->nullable()->after('phone');
        });
    }Error
    Call to undefined method App\Http\Controllers\TaskController::authorize()

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'csv_id',
                'csv_company_id', 
                'sector',
                'emtak',
                'phone',
                'website'
            ]);
        });
    }
};
