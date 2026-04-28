<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "primary reply account" is the mailbox used to continue conversations
 * after a cold-email lead has replied — typically the operator's main domain
 * address (e.g. veiko@webfight.ee). It is excluded from cold-email rotation
 * (InboxRotationService) and is the default From: when replying from the CRM.
 *
 * At most one account should carry this flag. The controller enforces the
 * single-primary invariant on write rather than via a partial unique index
 * (MySQL <= 8.0 does not support filtered unique indexes portably).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->boolean('is_primary_reply_account')
                  ->default(false)
                  ->after('is_active');

            $table->index('is_primary_reply_account', 'outreach_email_accounts_is_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->dropIndex('outreach_email_accounts_is_primary_idx');
            $table->dropColumn('is_primary_reply_account');
        });
    }
};
