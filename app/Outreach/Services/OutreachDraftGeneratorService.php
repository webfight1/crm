<?php

namespace App\Outreach\Services;

use App\Outreach\Models\OutreachLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OutreachDraftGeneratorService
 *
 * Given an outreach lead, fetches the lead's website context via
 * WebsiteContextService and asks OpenAI to produce a personalised
 * cold-email draft (3 subjects + body + follow-up + context summaries).
 * Persists the result to the lead's outreach_* fields and flips
 * outreach_generation_status accordingly.
 *
 * ── Cost / rate expectations ────────────────────────────────────────────────
 * Input:  ~1000-1500 tokens (context excerpt + prompt scaffolding)
 * Output: ~600-900 tokens (three subjects + two email bodies + summaries)
 * gpt-4o-mini → ~$0.001-0.002 per lead. A 100-lead batch → ~$0.15.
 *
 * ── Failure handling ────────────────────────────────────────────────────────
 * Every failure path (fetch error, OpenAI error, invalid JSON) writes
 * outreach_generation_status = failed + outreach_generation_error, and
 * returns false so the caller can display the row as errored in the UI.
 * The lead is NEVER left in the 'pending' state after generate() returns.
 *
 * ── No auto-send guarantee ──────────────────────────────────────────────────
 * This service only WRITES draft fields. It never dispatches jobs or
 * touches next_send_at. The operator-driven approve → send flow lives
 * in the controller + OutreachEmailService integration.
 */
class OutreachDraftGeneratorService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL   = 'gpt-4o-mini';
    private const TIMEOUT = 45;                 // seconds — LLM can be slow
    private const MAX_OUTPUT_TOKENS = 1400;

    public function __construct(
        private readonly WebsiteContextService $context,
    ) {}

    /**
     * Generate + persist a draft for one lead. Returns true on success.
     */
    public function generate(OutreachLead $lead): bool
    {
        $lead->update([
            'outreach_generation_status' => OutreachLead::DRAFT_PENDING,
            'outreach_generation_error'  => null,
        ]);

        // 1. Fetch website context
        $ctx = $this->context->fetch((string) $lead->website);
        if ($ctx['fetch_error']) {
            // We still let the LLM draft a message when the fetch fails —
            // it just falls back to a very general tone. That way a bad
            // URL doesn't block the whole batch.
            Log::info('[DraftGen] website fetch soft-fail', [
                'lead' => $lead->id, 'err' => $ctx['fetch_error'],
            ]);
        }

        // 2. Build prompt + call OpenAI
        $apiKey = config('services.openai.key');
        if (! $apiKey) {
            return $this->fail($lead, 'openai_key_missing');
        }

        $lead->loadMissing('campaign');
        $messages = $this->buildMessages($lead, $ctx, $lead->campaign);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(self::TIMEOUT)
                ->post(self::API_URL, [
                    'model'           => self::MODEL,
                    'messages'        => $messages,
                    'temperature'     => 0.7,
                    'max_tokens'      => self::MAX_OUTPUT_TOKENS,
                    'response_format' => ['type' => 'json_object'],
                ]);
        } catch (\Throwable $e) {
            return $this->fail($lead, 'openai_exception: ' . $e->getMessage());
        }

        if (! $response->successful()) {
            return $this->fail($lead, 'openai_http_' . $response->status() . ': ' . mb_substr($response->body(), 0, 400));
        }

        $raw = data_get($response->json(), 'choices.0.message.content');
        if (! $raw) {
            return $this->fail($lead, 'openai_empty_content');
        }

        // 3. Parse + validate JSON
        $draft = json_decode($raw, true);
        if (! is_array($draft)) {
            return $this->fail($lead, 'invalid_json: ' . mb_substr($raw, 0, 200));
        }

        $errors = $this->validate($draft);
        if ($errors) {
            return $this->fail($lead, 'schema: ' . implode(', ', $errors));
        }

        // 4. Persist. Status = ready → awaits operator review.
        // Body fields go through htmlize() — bodies are stored + sent as
        // HTML, so newlines (real or double-escaped literal "\\n" that
        // some LLMs emit) must become <br> or the reader sees "\n\n" in
        // plain text.
        //
        // PRESERVE APPROVED: if the operator already approved this draft
        // for sending, a --force regenerate MUST NOT silently demote it
        // back to 'ready' — that would flip the send pipeline over to the
        // step's placeholder template on the next dispatch. Regen updates
        // the body; the approval stands.
        $keepApproved = $lead->outreach_generation_status === OutreachLead::DRAFT_APPROVED;

        $lead->update([
            'outreach_subject_1'         => $this->str($draft, 'outreach_subject_1', 500),
            'outreach_subject_2'         => $this->str($draft, 'outreach_subject_2', 500),
            'outreach_subject_3'         => $this->str($draft, 'outreach_subject_3', 500),
            'website_context_summary'    => $this->str($draft, 'website_context_summary', 2000),
            'public_reference_context'   => $this->str($draft, 'public_reference_context', 2000),
            'seo_observation'            => $this->str($draft, 'seo_observation', 2000),
            'outreach_email_body'        => $this->htmlize($this->str($draft, 'outreach_email_body', 20000)),
            'outreach_followup_body'     => $this->htmlize($this->str($draft, 'outreach_followup_body', 20000)),
            'outreach_sources'           => $ctx['sources'] ?: null,
            'outreach_generation_status' => $keepApproved ? OutreachLead::DRAFT_APPROVED : OutreachLead::DRAFT_READY,
            'outreach_generation_error'  => null,
            'outreach_generated_at'      => now(),
        ]);

        return true;
    }

    // ─── prompt construction ─────────────────────────────────────────────────

    /** @return array{0:array,1:array} system+user message pair */
    private function buildMessages(OutreachLead $lead, array $ctx, ?\App\Outreach\Models\OutreachCampaign $campaign = null): array
    {
        // Design year: prefer stored design_year (Wayback), fall back to soft phrasing.
        $designHint = $lead->design_year
            ? "Kujundus tundub olevat pärit {$lead->design_year}. aasta kandist."
            : 'Kujundus tundub olevat varasemast ajast (täpne aasta teadmata).';

        $refLinks = array_slice(array_map(fn ($l) => $l['label'] . ' — ' . $l['url'], $ctx['reference_links']), 0, 6);
        $refBlock = $refLinks ? "Referentside/uudiste/projektide lingid:\n- " . implode("\n- ", $refLinks) : 'Referentside/uudiste/projektide linke ei leitud.';

        $contextBlock = trim(
            "Kodulehe URL: " . ($ctx['url'] ?? $lead->website) . "\n" .
            "Fetch-vea kood: " . ($ctx['fetch_error'] ?: 'ei ole') . "\n" .
            "Homepage title: " . ($ctx['title'] ?: '(puudub)') . "\n" .
            "Meta description: " . ($ctx['meta_description'] ?: '(puudub)') . "\n" .
            "H1: " . ($ctx['h1'] ?: '(puudub)') . "\n\n" .
            "Nähtava teksti väljavõte:\n" . ($ctx['text_excerpt'] ?: '(puudub)') . "\n\n" .
            $refBlock . "\n\n" .
            $designHint
        );

        $leadBlock = trim(
            "Ettevõtte nimi: " . ($lead->company ?: '(teadmata)') . "\n" .
            "Kontakti eesnimi: " . ($lead->first_name && strcasecmp($lead->first_name, 'Friend') !== 0 ? $lead->first_name : '(teadmata)') . "\n" .
            "Kontakti e-post: " . ($lead->email ?: '') . "\n" .
            "Tööstus: " . ($lead->industry ?: '(teadmata)')
        );

        $system = <<<'SYS'
Sa oled müügikirja assistent, kes koostab lühikesi eestikeelseid külma müügi e-kirju.
Reeglid:
- viisakas, loomulik, lühike, mitte agressiivne, mitte liiga müügimehelik
- ära kritiseeri otse — sõnasta pehmelt
- jäta mulje, et oled ettevõtet päriselt vaadanud
- ära leiuta fakte — kui infot on vähe, ütle üldiselt
- ära maini majandusandmeid, kasumit ega maksuvõlgu
- ära arvusta konkurente

Kirja struktuur (outreach_email_body):
1. Tervitus: "Tere [eesnimi või ettevõtte nimi/tiim]"
2. 1-2 lauset, mis ettevõtte veebilt või avalikust infost silma jäid
3. Pehme märkus disaini/kasutuskogemuse vanuse kohta (kasuta design_year hinnangut kui antud)
4. Küsi: "Kas teil on vahepeal tekkinud mõtet mõnda osa kodulehest paremaks teha või kogu veebi välimust ja kasutuskogemust värskendada?"
5. Üks lause: uue veebi puhul saaks paremini esile tuua teenused, referentsid, päringu teekonna ja Google'is leitavuse
6. Lõpp: "Terv.\nVeiko"

Follow-up kiri (outreach_followup_body):
- Sama toon, 2-3 lauset kõige rohkem
- Meenutab varem saadetud kirja
- Küsib pehmelt kas oli aega vaadata / kas oleks huvi lühikest ülevaadet saada
- Lõpp: "Terv.\nVeiko"

Vastus peab olema ainult JSON järgmise skeemiga:
{
  "outreach_subject_1": "...",
  "outreach_subject_2": "...",
  "outreach_subject_3": "...",
  "website_context_summary": "1-2 lauset selle kohta, mida saidilt leidsid",
  "public_reference_context": "1-2 lauset referentside / projektide / uudiste kohta või tühi kui ei leidnud",
  "seo_observation": "1 lause SEO/leidusvuse tähelepaneku kohta (üldine, mitte tehniline audit)",
  "outreach_email_body": "täielik kirja tekst (mitmerealine, \\n reavahetustega)",
  "outreach_followup_body": "follow-up kiri (mitmerealine)"
}

Kolm subjekti peavad olema erinevad stiililt (nt üks küsiv, üks konkreetne, üks vaba). Iga kuni 60 tähemärki.
Ära lisa ühtegi kommentaari väljapoole JSON-i. Ära paki JSON-i markdown-koodiblokki.
SYS;

        // Campaign-level extra guidance appended after the base rules but
        // before the JSON schema block, so the operator can nudge tone /
        // insertions (e.g. specific design_year phrasing) without breaking
        // the schema requirements. Placeholders resolved with strtr().
        $extra = trim((string) ($campaign?->draft_prompt_extra ?? ''));
        if ($extra !== '') {
            $extra = strtr($extra, [
                '{{company}}'      => (string) ($lead->company ?? ''),
                '{{website}}'      => (string) ($lead->website ?? ''),
                '{{industry}}'     => (string) ($lead->industry ?? ''),
                '{{first_name}}'   => (string) ($lead->first_name ?? ''),
                '{{last_name}}'    => (string) ($lead->last_name ?? ''),
                '{{email}}'        => (string) ($lead->email ?? ''),
                '{{design_year}}'  => (string) ($lead->design_year ?? ''),
                '{{design_age}}'   => (string) ($lead->design_age ?? ''),
            ]);
            $system .= "\n\nKAMPAANIA-SPETSIIFILISED JUHISED:\n" . $extra;
        }

        $user = "Ettevõtte info:\n{$leadBlock}\n\nVeebi kontekst:\n{$contextBlock}";

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ];
    }

    // ─── validation / persistence helpers ────────────────────────────────────

    private function validate(array $draft): array
    {
        $required = [
            'outreach_subject_1', 'outreach_subject_2', 'outreach_subject_3',
            'outreach_email_body',
        ];
        $errors = [];
        foreach ($required as $k) {
            if (! isset($draft[$k]) || trim((string) $draft[$k]) === '') {
                $errors[] = "missing $k";
            }
        }
        return $errors;
    }

    private function str(array $draft, string $key, int $max): ?string
    {
        $v = $draft[$key] ?? null;
        if (! is_string($v) || trim($v) === '') return null;
        return mb_substr(trim($v), 0, $max);
    }

    /**
     * Turn a plain-text body (with newlines OR literal "\n" that the LLM
     * emitted as escaped tokens) into HTML that renders as expected in
     * an email client. Order matters: strip literal backslash-n first,
     * then convert real newlines.
     */
    private function htmlize(?string $s): ?string
    {
        if ($s === null || $s === '') return $s;
        // If the model already returned real HTML tags, don't double-wrap.
        if (preg_match('/<(p|br|div|ul|ol|li|strong|em|a)\b/i', $s)) {
            return $s;
        }
        // Some models emit "\\n" inside their JSON string, which json_decode
        // preserves as a two-char literal — normalise both forms.
        $s = str_replace(['\\r\\n', '\\n'], "\n", $s);
        $s = str_replace(["\r\n", "\r"], "\n", $s);
        // Collapse 3+ blank lines into 2 (LLMs love empty paragraphs).
        $s = preg_replace("/\n{3,}/", "\n\n", $s);
        return nl2br(e(trim($s)), false);
    }

    private function fail(OutreachLead $lead, string $error): bool
    {
        Log::warning('[DraftGen] failed', ['lead' => $lead->id, 'error' => $error]);
        $lead->update([
            'outreach_generation_status' => OutreachLead::DRAFT_FAILED,
            'outreach_generation_error'  => mb_substr($error, 0, 1000),
        ]);
        return false;
    }
}
