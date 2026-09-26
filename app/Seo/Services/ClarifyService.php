<?php

namespace App\Seo\Services;

use App\Outreach\Models\OutreachLead;
use App\Seo\Models\SeoAudit;
use App\Seo\Playbook;

/**
 * The clarification round with a warm SEO lead:
 *
 *   draft()       — "is this the right page for <keyword>? any other keywords?"
 *                   Texts come from the Playbook (clarify.*). Stored on the lead
 *                   and pre-filled in the inbox reply form; never sent by itself.
 *   parseAnswer() — reads the client's answer: confirmed / other page / extra
 *                   keywords. Works without AI too (URLs picked by regex).
 */
class ClarifyService
{
    public function __construct(private readonly SeoAi $ai) {}

    /**
     * Is a sent e-mail the SEO draft (maybe edited), or some other mail to the
     * same client? Text similarity ≥ 50 %, or it carries the draft's link.
     */
    public static function isSameDraft(?string $draftHtml, ?string $sentHtml): bool
    {
        $norm = fn (?string $h) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $h))) ?? ''));
        $draft = mb_substr($norm($draftHtml), 0, 2000);
        $sent  = mb_substr($norm($sentHtml), 0, 2000);
        if ($draft === '' || $sent === '') {
            return false;
        }
        if (preg_match('~https?://\S+~u', (string) $draftHtml, $m) && str_contains((string) $sentHtml, rtrim($m[0], '".<>'))) {
            return true;
        }
        similar_text($draft, $sent, $percent);

        return $percent >= 50;
    }

    /** HTML body for the inbox reply editor. */
    public function draft(OutreachLead $lead, ?SeoAudit $audit): string
    {
        $landing = $audit && in_array($audit->page_source, ['csv', 'found', 'home'], true) ? $audit->url : null;

        $vars = [
            '{{name}}'        => $lead->first_name && $lead->first_name !== 'Friend' ? ', ' . $lead->first_name : '',
            '{{company}}'     => (string) $lead->company,
            '{{keyword}}'     => (string) $lead->serp_keyword,
            '{{website}}'     => (string) $lead->website,
            '{{landing_url}}' => (string) $landing,
        ];
        $vars['{{page_question}}'] = strtr(Playbook::get($landing ? 'clarify.page_found' : 'clarify.page_missing'), $vars);

        // Optional Search Console request — only when the operator's Google
        // account is set. A body saved before this placeholder existed gets
        // the request appended.
        $gscEmail = trim(Playbook::get('clarify.gsc_email'));
        $vars['{{gsc_request}}'] = $gscEmail !== ''
            ? strtr(Playbook::get('clarify.gsc_text'), ['{{gsc_email}}' => $gscEmail])
            : '';

        $body = Playbook::get('clarify.body');
        if ($vars['{{gsc_request}}'] !== '' && ! str_contains($body, '{{gsc_request}}')) {
            $body .= "\n\n{{gsc_request}}";
        }
        $text = strtr($body, $vars);

        // Plain text → paragraphs; bare URLs become links.
        $paragraphs = array_filter(array_map('trim', preg_split('/\R{2,}/u', trim($text)) ?: []), 'strlen');

        return implode("\n", array_map(function ($p) {
            $html = nl2br(e(trim($p)), false);
            $html = preg_replace('~(https?://[^\s<]+[^\s<.,;:!?)])~u', '<a href="$1">$1</a>', $html);
            return "<p>{$html}</p>";
        }, $paragraphs));
    }

    /**
     * @return array{is_answer:?bool, confirmed:?bool, url:?string, keywords:string[], summary:?string}
     *   is_answer — false when the mail is about something else (null = no AI)
     *   url — a page on the client's own site the client pointed to (absolute)
     */
    public function parseAnswer(OutreachLead $lead, string $answer, ?string $proposedUrl): array
    {
        $host = $this->host($lead->website ?: $proposedUrl ?: '');

        $ai = $this->ai->json(
            "Klient vastas SEO-pakkuja täpsustuskirjale. Kirjas küsiti: (1) kas märksõnale vastab pakutud leht või mõni muu; "
            . "(2) kas huvitavad ka muud märksõnad/teenused.\n"
            . "Kõigepealt otsusta, kas kiri üldse vastab neile küsimustele (is_answer). Kiri muust teemast (hind, kohtumine, "
            . "üldine küsimus, automaatvastus) → is_answer=false.\n"
            . "Vasta JSON-ina: {\"is_answer\": true|false, \"page_confirmed\": true|false|null, \"page_url\": \"kliendi nimetatud lehe URL või null\", "
            . "\"extra_keywords\": [\"otsingufraas\", …], \"summary\": \"üks lause eesti keeles\"}.\n"
            . "page_confirmed=true ainult siis, kui klient kinnitab pakutud lehte. extra_keywords: kirjuta need otsingufraasidena, "
            . "nagu inimene Google'isse trükiks (nt \"katuse remont tartu\"), max 5. Kui klient teenuseid ei nimeta, jäta tühjaks.",
            "Märksõna: {$lead->serp_keyword}\nPakutud leht: " . ($proposedUrl ?: '(ei leitud)') . "\nKliendi koduleht: {$lead->website}\n\nVastus:\n{$answer}",
            400,
        );

        // Any URL on the client's own domain in the reply wins over the AI's reading.
        $url = null;
        if (preg_match_all('~https?://[^\s<>"\')]+~iu', $answer, $m)) {
            foreach ($m[0] as $u) {
                if ($host && $this->host($u) === $host) {
                    $url = rtrim($u, '.,;:!?');
                    break;
                }
            }
        }
        if (! $url && is_string($ai['page_url'] ?? null)) {
            $u = trim($ai['page_url']);
            if (str_starts_with($u, '/') && $lead->website) {
                $u = rtrim($this->origin($lead->website), '/') . $u;
            }
            if ($host && $this->host($u) === $host) {
                $url = $u;
            }
        }
        if ($url && $proposedUrl && rtrim($url, '/') === rtrim($proposedUrl, '/')) {
            $url = null; // same page — that's a confirmation, not a new page
            $ai['page_confirmed'] = true;
        }

        $keywords = array_values(array_unique(array_filter(array_map(
            fn ($k) => is_string($k) ? mb_substr(trim($k), 0, 100) : '',
            (array) ($ai['extra_keywords'] ?? [])
        ))));

        return [
            // A URL on their own site counts as an answer whatever the AI says.
            'is_answer' => $url ? true : (is_bool($ai['is_answer'] ?? null) ? $ai['is_answer'] : null),
            'confirmed' => is_bool($ai['page_confirmed'] ?? null) ? $ai['page_confirmed'] : null,
            'url'       => $url,
            'keywords'  => array_slice($keywords, 0, 5),
            'summary'   => is_string($ai['summary'] ?? null) ? mb_substr($ai['summary'], 0, 250) : null,
        ];
    }

    private function host(string $url): string
    {
        if ($url !== '' && ! preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }

        return preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    private function origin(string $url): string
    {
        if (! preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        $p = parse_url($url);

        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');
    }
}
