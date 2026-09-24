<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('last_reply_check_uid')
                ->nullable()
                ->after('last_error');
            $table->timestamp('last_reply_checked_at')
                ->nullable()
                ->after('last_reply_check_uid');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->dropColumn(['last_reply_check_uid', 'last_reply_checked_at']);
        });
    }
};
