<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEO audit branch: e-shop vs regular website.
 *
 *   seo_audits.site_type       — eshop | service (detected, or set by the operator)
 *   seo_audits.site_type_note  — detection signals
 *   seo_audits.extras          — structured detail, e.g. {"categories": [...]}
 *   seo_audit_checks.applies_to — all | service | eshop
 *   + e-shop checks: category names vs searches, Product schema, category text (AI)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_audits', function (Blueprint $table) {
            $table->string('site_type', 10)->nullable()->after('page_note');
            $table->string('site_type_note', 500)->nullable()->after('site_type');
            $table->json('extras')->nullable()->after('results');
        });

        Schema::table('seo_audit_checks', function (Blueprint $table) {
            $table->string('applies_to', 10)->default('all')->after('type');
        });

        $now = now();
        $row = fn (string $key, string $type, string $label, ?string $question, string $explanation, int $weight, ?string $fix, ?float $price, int $order) => [
            'key' => $key, 'type' => $type, 'applies_to' => 'eshop', 'label' => $label, 'question' => $question,
            'client_explanation' => $explanation, 'enabled' => true, 'weight' => $weight,
            'fix_title' => $fix, 'fix_price' => $price, 'fix_quantity' => 1, 'fix_unit' => 'tk',
            'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
        ];

        $rows = [
            $row('eshop_category_keywords', 'builtin', 'Kategooriate nimed vastavad otsingutele', null,
                'Kategooria on e-poe tähtsaim maandumisleht. Kui see on nimetatud teisiti kui inimesed otsivad, ei seo Google seda otsinguga.',
                4, 'Kategooriate nimede ja pealkirjade optimeerimine otsingusõnade järgi', 250, 6),
            $row('eshop_product_schema', 'builtin', 'Tootelehtedel on Product-andmed (schema.org)', null,
                'Siis näitab Google otsingus hinda, saadavust ja hinnanguid — see tõstab klikke.',
                2, 'Tooteandmete (Product schema) seadistamine', 150, 7),
            $row('ai_eshop_category_text', 'ai', 'Kategoorialehel on tutvustav tekst',
                'Kas lehel on lisaks tootenimekirjale vähemalt paar lauset tutvustavat teksti selle kategooria kohta?',
                'Ainult tootenimekirjaga lehel pole Google\'il piisavalt teksti, et aru saada, mille kohta leht on.',
                3, 'Kategooriate tutvustustekstid (5 kategooriat)', 200, 8),
        ];
        foreach ($rows as $r) {
            if (! DB::table('seo_audit_checks')->where('key', $r['key'])->exists()) {
                DB::table('seo_audit_checks')->insert($r);
            }
        }
    }

    public function down(): void
    {
        DB::table('seo_audit_checks')->whereIn('key', ['eshop_category_keywords', 'eshop_product_schema', 'ai_eshop_category_text'])->delete();

        Schema::table('seo_audit_checks', function (Blueprint $table) {
            $table->dropColumn('applies_to');
        });

        Schema::table('seo_audits', function (Blueprint $table) {
            $table->dropColumn(['site_type', 'site_type_note', 'extras']);
        });
    }
};
