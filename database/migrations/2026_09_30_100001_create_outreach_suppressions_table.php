<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Global do-not-contact list for Outreach: an e-mail or a whole domain here
 * is never imported into, or sent from, any campaign. Seeded from every lead
 * that already unsubscribed, bounced or answered "not interested".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_suppressions', function (Blueprint $table) {
            $table->id();
            // Lower-case e-mail ("info@foo.ee") or domain ("foo.ee").
            $table->string('value')->unique();
            $table->string('type', 10);    // email | domain
            $table->string('reason', 20);  // unsubscribed | bounced | not_interested | manual
            $table->foreignId('lead_id')->nullable()->constrained('outreach_leads')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        $now = now();
        $rows = DB::table('outreach_leads')
            ->where(fn ($q) => $q->whereIn('status', ['unsubscribed', 'bounced'])->orWhere('reply_intent', 'not_interested'))
            ->orderBy('id')
            ->get(['id', 'email', 'status', 'reply_intent'])
            ->unique(fn ($l) => strtolower(trim($l->email)))
            ->map(fn ($l) => [
                'value'      => strtolower(trim($l->email)),
                'type'       => 'email',
                'reason'     => in_array($l->status, ['unsubscribed', 'bounced'], true) ? $l->status : 'not_interested',
                'lead_id'    => $l->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        foreach ($rows->chunk(500) as $chunk) {
            DB::table('outreach_suppressions')->insertOrIgnore($chunk->values()->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_suppressions');
    }
};
