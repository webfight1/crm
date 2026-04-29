<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds support for HTTP-relay sending — required when an upstream host
 * (e.g. Zone.ee shared hosting) blocks outbound SMTP from a remote VPS but
 * allows local PHP mail() on the host that owns the domain.
 *
 * Flow:
 *   CRM (VPS) ──HTTPS POST──▶ webfight.ee/mail-relay.php ──PHP mail()──▶ recipient
 *
 * The relay endpoint authenticates each request via HMAC-SHA256 over
 * (timestamp + raw body) using the shared secret stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->string('relay_url')->nullable()->after('imap_encryption');
            // Encrypted at rest via the model accessor, same approach as smtp_password.
            $table->text('relay_secret')->nullable()->after('relay_url');
        });
    }

    public function down(): void
    {
        Schema::table('outreach_email_accounts', function (Blueprint $table) {
            $table->dropColumn(['relay_url', 'relay_secret']);
        });
    }
};
