<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEO Playbook — the operator-editable rules of the SEO sales pipeline.
 *
 *   seo_playbook_settings — key/value store for filters, AI guidelines,
 *                           automation switches and offer defaults. Keys and
 *                           their defaults are declared in App\Seo\Playbook;
 *                           a missing row simply means "use the default".
 *   seo_audit_checks      — the audit checklist. 'builtin' rows are measured
 *                           in code (PageAnalyzer); 'ai' rows are plain-language
 *                           questions the operator writes and the LLM answers
 *                           from the page content. Each row can carry a fix
 *                           with a price, which becomes a quotation line.
 *   seo_audits            — one audit run for a URL, optionally tied to an
 *                           outreach lead / CRM deal and the quotation made
 *                           from it.
 *   outreach_leads.*      — reply intent + the Customer/Deal created when an
 *                           SEO lead answers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_playbook_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        Schema::create('seo_audit_checks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 16)->default('ai');          // builtin | ai
            $table->string('label');
            $table->text('question')->nullable();               // ai: what to ask about the page
            $table->text('client_explanation')->nullable();     // why it matters, in client language
            $table->boolean('enabled')->default(true);
            $table->unsignedTinyInteger('weight')->default(2);  // 1 (minor) … 5 (critical)
            $table->string('fix_title')->nullable();            // quotation line text
            $table->decimal('fix_price', 10, 2)->nullable();
            $table->decimal('fix_quantity', 8, 2)->default(1);
            $table->string('fix_unit', 50)->default('tk');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('seo_audits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable()->index();
            $table->unsignedBigInteger('deal_id')->nullable()->index();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->string('url');
            $table->string('keyword')->nullable();
            $table->string('status', 16)->default('pending');   // pending | done | failed
            $table->unsignedTinyInteger('score')->nullable();   // 0-100, weighted
            $table->json('results')->nullable();                // [{key,label,status,value,note}, …]
            $table->text('summary')->nullable();                // client-friendly AI summary
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->string('reply_intent', 20)->nullable()->after('replied_at');
            $table->string('reply_intent_reason')->nullable()->after('reply_intent');
            $table->unsignedBigInteger('customer_id')->nullable()->after('reply_intent_reason');
            $table->unsignedBigInteger('deal_id')->nullable()->after('customer_id');
        });

        $now = now();
        $order = 0;
        $row = fn (string $key, string $label, int $weight, string $explanation, ?string $fix, ?float $price, string $type = 'builtin', ?string $question = null, bool $enabled = true)
            => [
                'key' => $key, 'type' => $type, 'label' => $label, 'question' => $question,
                'client_explanation' => $explanation, 'enabled' => $enabled, 'weight' => $weight,
                'fix_title' => $fix, 'fix_price' => $price, 'fix_quantity' => 1, 'fix_unit' => 'tk',
                'sort_order' => $order += 10, 'created_at' => $now, 'updated_at' => $now,
            ];

        // Starting checklist. Prices are placeholders — edit them in the Playbook.
        DB::table('seo_audit_checks')->insert([
            $row('https', 'Leht kasutab HTTPS-i', 5, 'Google eelistab turvalisi lehti ja brauser hoiatab külastajat ilma HTTPS-ita.', 'HTTPS seadistamine ja ümbersuunamised', 60),
            $row('noindex', 'Leht pole indekseerimisest välja jäetud', 5, 'Kui leht on märgitud "noindex", ei näita Google seda üldse.', 'Indekseerimise vigade parandus', 40),
            $row('title', 'Lehe pealkiri (title) on olemas ja õige pikkusega', 4, 'Pealkiri on see, mida inimene Google\'i tulemustes esimesena näeb.', 'Lehtede pealkirjade optimeerimine', 80),
            $row('keyword_in_title', 'Märksõna on lehe pealkirjas', 4, 'Kui otsitav fraas pole pealkirjas, on Google\'il raskem aru saada, millest leht räägib.', 'Märksõnapõhine pealkirjade ümberkirjutus', 60),
            $row('meta_description', 'Meta kirjeldus on olemas ja õige pikkusega', 3, 'Kirjeldus mõjutab, kas inimene klikib just sinu tulemusele.', 'Meta kirjelduste kirjutamine', 80),
            $row('h1', 'Lehel on täpselt üks H1 pealkiri', 3, 'Selge põhipealkiri aitab nii Google\'il kui külastajal lehe teemat mõista.', 'Pealkirjastruktuuri korrastamine', 50),
            $row('keyword_in_h1', 'Märksõna on H1 pealkirjas', 3, 'Põhipealkiri on tugevaim signaal lehe teemast.', null, null),
            $row('word_count', 'Lehel on piisavalt sisu (≥ 300 sõna)', 3, 'Õhukese sisuga lehed jäävad konkurentidele alla.', 'Sisuteksti kirjutamine (1 leht)', 150),
            $row('viewport', 'Leht on mobiilisõbralik (viewport)', 4, 'Enamik otsinguid tehakse telefonist ja Google hindab mobiilivaadet.', 'Mobiilivaate parandused', 120),
            $row('pagespeed_mobile', 'Mobiilikiirus (PageSpeed ≥ 50)', 3, 'Aeglane leht kaotab külastajaid ja positsioone.', 'Kiiruse optimeerimine', 200),
            $row('image_alt', 'Piltidel on alt-tekstid', 1, 'Alt-tekstid aitavad pildiotsingus ja ligipääsetavuses.', 'Piltide alt-tekstide lisamine', 40),
            $row('canonical', 'Canonical-silt on olemas', 1, 'Väldib duplikaatsisu probleeme.', null, null),
            $row('schema', 'Struktureeritud andmed (schema.org)', 2, 'Aitab Google\'il näidata rikkalikumaid tulemusi (nt kontakt, arvustused).', 'Struktureeritud andmete lisamine', 90),
            $row('sitemap', 'Sitemap.xml on olemas', 2, 'Sitemap aitab Google\'il kõik lehed üles leida.', 'Sitemapi seadistamine + Search Console', 40),
            $row('robots_txt', 'Robots.txt on olemas', 1, 'Annab otsingumootoritele juhised, mida indekseerida.', null, null),
            $row('ai_contact_cta', 'Avalehel on selge päringu/kontakti võimalus', 2, 'Kui külastaja ei leia kiiresti, kuidas ühendust võtta, jääb päring saamata.', 'Päringuvormi / CTA parendus', 80,
                'ai', 'Kas avalehel on selgelt nähtav telefon, e-post või päringuvorm ja üleskutse ühendust võtta?'),
        ]);
    }

    public function down(): void
    {
        Schema::table('outreach_leads', function (Blueprint $table) {
            $table->dropColumn(['reply_intent', 'reply_intent_reason', 'customer_id', 'deal_id']);
        });
        Schema::dropIfExists('seo_audits');
        Schema::dropIfExists('seo_audit_checks');
        Schema::dropIfExists('seo_playbook_settings');
    }
};
