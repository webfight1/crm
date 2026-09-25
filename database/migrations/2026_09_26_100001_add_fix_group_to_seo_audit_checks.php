<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * seo_audit_checks.fix_group — fixes in the same group become ONE quotation
 * line (price = sum), so an offer reads "Tehniline SEO korrastus" instead of
 * eight small lines. Empty group = own line. Editable in the Playbook.
 */
return new class extends Migration
{
    private const GROUPS = [
        'Tehniline SEO korrastus'                => ['https', 'noindex', 'canonical', 'robots_txt', 'sitemap', 'schema', 'eshop_product_schema'],
        'Pealkirjade ja kirjelduste optimeerimine' => ['title', 'keyword_in_title', 'meta_description', 'h1', 'keyword_in_h1', 'image_alt'],
        'Sisu ja teenuselehed'                   => ['word_count', 'keyword_landing_page'],
        'Kiirus ja mobiilivaade'                 => ['pagespeed_mobile', 'viewport'],
        'E-poe kategooriate optimeerimine'       => ['eshop_category_keywords', 'ai_eshop_category_text'],
    ];

    public function up(): void
    {
        Schema::table('seo_audit_checks', function (Blueprint $table) {
            $table->string('fix_group', 100)->nullable()->after('fix_title');
        });

        foreach (self::GROUPS as $group => $keys) {
            DB::table('seo_audit_checks')->whereIn('key', $keys)->update(['fix_group' => $group]);
        }
    }

    public function down(): void
    {
        Schema::table('seo_audit_checks', function (Blueprint $table) {
            $table->dropColumn('fix_group');
        });
    }
};
