<?php

namespace App\Seo;

use App\Seo\Models\SeoPlaybookSetting;

/**
 * SEO Playbook — every rule of the SEO sales pipeline that the operator may
 * want to tune lives here instead of in code.
 *
 * DEFINITIONS is the single source of truth: section, label, type, default and
 * help text. The /seo/playbook page renders straight from it, so adding a new
 * tunable rule = add one entry here and read it with Playbook::get().
 *
 * Values are stored as strings in seo_playbook_settings; a missing row means
 * the default applies. Types:
 *   text | textarea — free text
 *   int             — integer
 *   bool            — "1" / "0"
 *   lines           — one item per line; Playbook::lines() returns a trimmed array
 */
class Playbook
{
    public const SECTIONS = [
        'filter' => 'Leadide filter (CSV import)',
        'ai'     => 'AI juhised',
        'auto'   => 'Automaatika',
        'clarify' => 'Täpsustuskiri soojale kliendile',
        'eshop'   => 'E-pood',
        'content' => 'Sisuturundus (blogi)',
        'offer'  => 'Pakkumine',
    ];

    public const DEFINITIONS = [
        // ── Filter ──────────────────────────────────────────────────────────
        'filter.position_min' => [
            'section' => 'filter', 'type' => 'int', 'default' => '11',
            'label'   => 'Min positsioon',
            'help'    => 'Leadid, kes on sellest kõrgemal (nt 1–10 = juba esilehel), märgitakse importimisel "skip".',
        ],
        'filter.position_max' => [
            'section' => 'filter', 'type' => 'int', 'default' => '50',
            'label'   => 'Max positsioon',
            'help'    => 'Sügavamal olijad märgitakse "skip". 0 = piirang puudub.',
        ],
        'filter.exclude_domains' => [
            'section' => 'filter', 'type' => 'lines', 'default' => '',
            'label'   => 'Välistatud domeenid / sõnad',
            'help'    => 'Üks rea kohta. Kui lehe aadress või e-post sisaldab seda, lead jäetakse vahele (nt olemasolevad kliendid, konkurendid).',
        ],
        'filter.column_aliases' => [
            'section' => 'filter', 'type' => 'lines',
            'default' => "märksõna = keyword\nfraas = keyword\npositsioon = position\nkoht = position\nleht = google_page\nurl = website\nreastuv leht = ranking_url\nlanding page = ranking_url\nranking url = ranking_url\ndomeen = website\nkodulehekülg = website\ne-post = email\nettevõte = company",
            'label'   => 'CSV veerunimede vasted',
            'help'    => 'Kujul "veerg sinu failis = süsteemi väli". Nii saab SEO-monitori ekspordi otse üles laadida. Väljad: email, company, website, keyword, position, google_page, competitors, ranking_url (leht, mida Google märksõnale näitab), first_name, industry.',
        ],

        // ── AI ──────────────────────────────────────────────────────────────
        'ai.model' => [
            'section' => 'ai', 'type' => 'text', 'default' => 'gpt-4o-mini',
            'label'   => 'AI mudel',
            'help'    => 'OpenAI mudel, mida kasutatakse vastuste liigitamiseks, auditi kokkuvõtteks ja AI-kontrollideks.',
        ],
        'ai.email_guidelines' => [
            'section' => 'ai', 'type' => 'textarea',
            'default' => "Sinatame. Max 6 lauset. Ära luba kunagi garanteeritud esikohta.\nMaini konkreetset märksõna ja positsiooni, mitte üldist \"SEO-d\".",
            'label'   => 'Müügikirja juhised',
            'help'    => 'Lisatakse AI mustandi juhistele kõigil leadidel, kellel on märksõna (SEO-leadid). Kampaania enda lisajuhised kehtivad edasi ja on tähtsamad.',
        ],
        'ai.reply_guidelines' => [
            'section' => 'ai', 'type' => 'textarea',
            'default' => 'Kui inimene küsib hinda, aega või detaile, on ta huvitatud. "Saatke infot" = huvitatud.',
            'label'   => 'Vastuste liigitamise juhised',
            'help'    => 'Aitab AI-l otsustada, kas vastaja on huvitatud, hiljem huvitatud või mitte.',
        ],
        'ai.audit_guidelines' => [
            'section' => 'ai', 'type' => 'textarea',
            'default' => "Kirjuta kliendile arusaadavalt, ilma žargoonita. Alusta kõige suurema mõjuga probleemist.\nMax 3 lõiku. Ära hirmuta, ole konkreetne.",
            'label'   => 'Auditi kokkuvõtte juhised',
            'help'    => 'Kuidas AI auditi tulemused kliendile kokku võtab (see tekst läheb pakkumise kirjeldusse).',
        ],

        'ai.category_guidelines' => [
            'section' => 'ai', 'type' => 'textarea',
            'default' => "Hea nimi = sama sõnastus, mida inimesed Google'is kasutavad (levinuim sünonüüm, mitmus/ainsus nagu otsingutes).\nBrändinimed, „Soodustus“, „Uued tooted“, „Muu“ jms: ok=true, neid ei hinda.\nParem nimi olgu lühike ja loomulik, mitte märksõnadega üle kuhjatud.",
            'label'   => 'E-poe kategooriate hindamise juhised',
            'help'    => 'Kuidas AI otsustab, kas kategooria nimi vastab sellele, mida inimesed otsivad (võrdleb Google\'i otsingusoovitustega).',
        ],

        // ── Automation ──────────────────────────────────────────────────────
        'auto.classify_replies' => [
            'section' => 'auto', 'type' => 'bool', 'default' => '1',
            'label'   => 'Liigita SEO-leadide vastused AI-ga',
            'help'    => 'Kehtib leadidele, kellel on märksõna (serp_keyword).',
        ],
        'auto.warm_intents' => [
            'section' => 'auto', 'type' => 'lines', 'default' => "interested\nunclear",
            'label'   => 'Millised vastused teevad soojaks kliendiks',
            'help'    => 'Võimalikud: interested, later, unclear, not_interested, auto_reply. "unclear" = AI pole kindel, parem sul üle vaadata.',
        ],
        'auto.create_client' => [
            'section' => 'auto', 'type' => 'bool', 'default' => '1',
            'label'   => 'Loo soojale leadile klient + tehing CRM-is',
            'help'    => '',
        ],
        'auto.deal_stage' => [
            'section' => 'auto', 'type' => 'text', 'default' => 'qualified',
            'label'   => 'Uue tehingu etapp',
            'help'    => 'lead, qualified, proposal, negotiation …',
        ],
        'auto.audit_on_warm' => [
            'section' => 'auto', 'type' => 'bool', 'default' => '1',
            'label'   => 'Käivita soojale kliendile automaatselt audit',
            'help'    => '',
        ],
        'auto.offer_after_audit' => [
            'section' => 'auto', 'type' => 'bool', 'default' => '1',
            'label'   => 'Koosta auditi järel pakkumise MUSTAND',
            'help'    => 'Pakkumist ei saadeta kunagi automaatselt — see jääb "draft" staatusesse sinu ülevaatuseks.',
        ],
        'auto.owner_user_id' => [
            'section' => 'auto', 'type' => 'int', 'default' => '0',
            'label'   => 'Tehingute omanik (kasutaja ID)',
            'help'    => '0 = esimene kasutaja süsteemis.',
        ],

        // ── Clarify ─────────────────────────────────────────────────────────
        'clarify.enabled' => [
            'section' => 'clarify', 'type' => 'bool', 'default' => '1',
            'label'   => 'Koosta soojale kliendile täpsustuskirja mustand',
            'help'    => 'Kiri küsib, kas leitud leht on õige ja kas huvitavad ka muud märksõnad. Mustand ilmub postkastis vastamisvormi — saadad SINA.',
        ],
        'clarify.wait_for_answer' => [
            'section' => 'clarify', 'type' => 'bool', 'default' => '1',
            'label'   => 'Pakkumise mustand alles pärast kliendi vastust',
            'help'    => 'Audit tehakse kohe, et sul oleks info olemas. Kui klient nimetab vastuses teise lehe, auditeeritakse see uuesti ja pakkumine tehakse selle põhjal.',
        ],
        'clarify.body' => [
            'section' => 'clarify', 'type' => 'textarea',
            'default' => "Tere{{name}}!\n\nAitäh vastuse eest! Et analüüs oleks täpne, täpsustan kahte asja:\n\n1. {{page_question}}\n\n2. Kas lisaks fraasile „{{keyword}}“ on veel teenuseid või otsingusõnu, mille järgi tahaksite Google'is paremini leitav olla?\n\n{{gsc_request}}\n\nPiisab paarist sõnast — siis saadan konkreetse ülevaate.",
            'label'   => 'Kirja tekst',
            'help'    => 'Kohatäited: {{name}} (", Mari" või tühi), {{company}}, {{keyword}}, {{website}}, {{landing_url}}, {{page_question}} (üks kahest allolevast lausest), {{gsc_request}} (Search Console\'i palve, vt allpool — kui seda kohatäidet tekstis pole, lisatakse palve kirja lõppu).',
        ],
        'clarify.gsc_email' => [
            'section' => 'clarify', 'type' => 'text', 'default' => '',
            'label'   => 'Sinu Google\'i konto Search Console\'i jaoks',
            'help'    => 'Sama konto, millega SEO-monitoris Google\'i ühendad. Tühi = kirjas Search Console\'i ei küsita.',
        ],
        'clarify.gsc_text' => [
            'section' => 'clarify', 'type' => 'textarea',
            'default' => "Kui soovite täpsemat analüüsi, lisage mind oma Google Search Console'i kasutajaks — see võtab paar minutit ja annab mulle ainult andmete vaatamise õiguse, mitte ligipääsu teie kodulehele:\nSearch Console → Seaded → Kasutajad ja load → Lisa kasutaja → {{gsc_email}} → õigus „Piiratud“.\nKui teil Search Console'i pole või see tundub keeruline, pole hullu — saan analüüsi teha ka ilma.",
            'label'   => '{{gsc_request}} tekst',
            'help'    => 'Kohatäide {{gsc_email}}. „Piiratud“ = ainult vaatamine; „Täielik“ küsi alles tasulise töö alguses.',
        ],
        'clarify.page_found' => [
            'section' => 'clarify', 'type' => 'textarea',
            'default' => 'Fraasile „{{keyword}}“ vastab teie kodulehel minu hinnangul see leht: {{landing_url}} — kas see on õige leht, mida soovite Google\'is kõrgemale tõsta?',
            'label'   => '{{page_question}}, kui leht leiti',
            'help'    => '',
        ],
        'clarify.page_missing' => [
            'section' => 'clarify', 'type' => 'textarea',
            'default' => 'Ma ei leidnud teie kodulehelt fraasile „{{keyword}}“ eraldi lehte. Kas selline leht on olemas? Kui jah, saatke palun link.',
            'label'   => '{{page_question}}, kui lehte ei leitud',
            'help'    => '',
        ],

        // ── E-shop ──────────────────────────────────────────────────────────
        'eshop.max_categories' => [
            'section' => 'eshop', 'type' => 'int', 'default' => '15',
            'label'   => 'Mitu kategooriat analüüsida',
            'help'    => 'Iga kategooria kohta küsitakse Google\'i otsingusoovitused. Rohkem = täpsem, aga aeglasem.',
        ],
        'eshop.category_match_pct' => [
            'section' => 'eshop', 'type' => 'int', 'default' => '70',
            'label'   => 'Kategooriate kontroll on korras alates (%)',
            'help'    => 'Kui suur osa kategooriatest peab olema sõnastatud nagu otsingud.',
        ],

        // ── Content ─────────────────────────────────────────────────────────
        'content.enabled' => [
            'section' => 'content', 'type' => 'bool', 'default' => '1',
            'label'   => 'Lisa pakkumisse sisuturundus, kui leht on tehniliselt korras',
            'help'    => 'Blogi puudub → blogi loomine. Blogi olemas → igakuine artiklipakett. Audit tuvastab blogi ise.',
        ],
        'content.min_score' => [
            'section' => 'content', 'type' => 'int', 'default' => '80',
            'label'   => '„Tehniliselt korras“ alates auditi skoorist',
            'help'    => '0 = lisa sisuturundus alati, ka koos tehniliste parandustega.',
        ],
        'content.blog_setup' => [
            'section' => 'content', 'type' => 'lines',
            'default' => "Blogi loomine ja seadistamine (rubriik, mall, SEO seaded) | 1 | tk | 350\nEsimesed SEO-artiklid märksõna „{{keyword}}“ teemal | 2 | artiklit | 90",
            'label'   => 'Read, kui blogi PUUDUB',
            'help'    => 'Kujul "kirjeldus | kogus | ühik | hind". Kohatäited: {{keyword}}, {{company}}.',
        ],
        'content.articles' => [
            'section' => 'content', 'type' => 'lines',
            'default' => 'SEO-artikkel blogisse, 1× nädalas (märksõnauuring, tekst, pildid, avaldamine) | 4 | artiklit/kuu | 90',
            'label'   => 'Read, kui blogi ON OLEMAS',
            'help'    => 'Kujul "kirjeldus | kogus | ühik | hind". Kohatäited: {{keyword}}, {{company}}.',
        ],

        // ── Offer ───────────────────────────────────────────────────────────
        'offer.title' => [
            'section' => 'offer', 'type' => 'text', 'default' => 'SEO parendamise pakkumine – {{company}}',
            'label'   => 'Pakkumise pealkiri',
            'help'    => 'Kohatäited: {{company}}, {{keyword}}, {{position}}, {{website}}',
        ],
        'offer.base_items' => [
            'section' => 'offer', 'type' => 'lines', 'default' => 'SEO lähteanalüüs ja märksõnastrateegia | 1 | tk | 150',
            'label'   => 'Alati lisatavad read',
            'help'    => 'Kujul "kirjeldus | kogus | ühik | hind". Lisanduvad auditi leidude ridadele.',
        ],
        'offer.group_items' => [
            'section' => 'offer', 'type' => 'bool', 'default' => '1',
            'label'   => 'Koonda sama grupi parandused üheks reaks',
            'help'    => 'Grupi määrad iga kontrolli juures („Grupp pakkumises“). Rea hind = grupi paranduste summa, kirjeldusse loetletakse tööd.',
        ],
        'offer.valid_days' => [
            'section' => 'offer', 'type' => 'int', 'default' => '14',
            'label'   => 'Kehtivus (päeva)',
            'help'    => '',
        ],
        'offer.notes' => [
            'section' => 'offer', 'type' => 'textarea', 'default' => '',
            'label'   => 'Pakkumise märkused',
            'help'    => 'Lisatakse iga automaatse pakkumise märkuste välja.',
        ],
    ];

    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    public static function get(string $key): string
    {
        $stored = self::all()[$key] ?? null;

        return $stored ?? (string) (self::DEFINITIONS[$key]['default'] ?? '');
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    /** @return string[] */
    public static function lines(string $key): array
    {
        return self::parseLines(self::get($key));
    }

    /** @return string[] */
    public static function parseLines(string $value): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $value) ?: []),
            fn ($l) => $l !== '' && ! str_starts_with($l, '#')
        ));
    }

    public static function set(string $key, ?string $value): void
    {
        if (! isset(self::DEFINITIONS[$key])) {
            throw new \InvalidArgumentException("Unknown playbook key: {$key}");
        }

        if (self::DEFINITIONS[$key]['type'] === 'bool') {
            $value = $value ? '1' : '0';
        }

        SeoPlaybookSetting::updateOrCreate(['key' => $key], ['value' => $value ?? '']);
        self::$cache = null;
    }

    /** @return array<string, string|null> */
    private static function all(): array
    {
        if (self::$cache === null) {
            try {
                self::$cache = SeoPlaybookSetting::pluck('value', 'key')->all();
            } catch (\Throwable) {
                // Table not migrated yet (fresh deploy) — run on defaults.
                self::$cache = [];
            }
        }

        return self::$cache;
    }

    /** Forget the per-request cache (queue workers are long-lived). */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
