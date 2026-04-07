<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // Display name, e.g. "John at Acme"
            $table->string('email')->unique();

            // Provider: gmail | smtp | outlook
            $table->string('provider')->default('smtp');

            // SMTP outbound credentials
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();      // Encrypted at rest
            $table->string('smtp_encryption')->default('tls'); // tls | ssl | none

            // IMAP inbound credentials (for reply detection)
            $table->string('imap_host')->nullable();
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_username')->nullable();
            $table->text('imap_password')->nullable();      // Encrypted at rest
            $table->string('imap_encryption')->default('ssl'); // ssl | tls | none

            // Sending limits
            $table->unsignedSmallInteger('daily_limit')->default(30);
            $table->unsignedSmallInteger('sent_today')->default(0);
            $table->timestamp('last_sent_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sent_today', 'last_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_email_accounts');
    }
};
