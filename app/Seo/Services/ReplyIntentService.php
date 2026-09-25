<?php

namespace App\Seo\Services;

use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Seo\Playbook;

/**
 * Classifies an outreach lead's latest inbound reply into one intent:
 *   interested | later | not_interested | auto_reply | unclear
 * Guidance comes from Playbook ai.reply_guidelines. No AI → 'unclear',
 * which by default still routes the lead to a human.
 */
class ReplyIntentService
{
    public const INTENTS = ['interested', 'later', 'not_interested', 'auto_reply', 'unclear'];

    public const LABELS = [
        'interested'     => 'Huvitatud',
        'later'          => 'Hiljem',
        'not_interested' => 'Pole huvitatud',
        'auto_reply'     => 'Automaatvastus',
        'unclear'        => 'Ebaselge',
    ];

    public function __construct(private readonly SeoAi $ai) {}

    /** @return array{intent:string, reason:?string} */
    public function classify(OutreachLead $lead): array
    {
        $message = OutreachMessage::where('lead_id', $lead->id)
            ->where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->latest('received_at')
            ->first();

        if (! $message) {
            return ['intent' => 'unclear', 'reason' => 'Vastuse sisu ei leitud.'];
        }

        $body = trim((string) ($message->body_text ?: strip_tags((string) $message->body_html)));
        // Drop the quoted original — only the new part matters.
        $body = mb_substr(preg_split('/\n\s*(>|On .+ wrote:|.+ kirjutas:)/u', $body)[0] ?? $body, 0, 3000);

        $answer = $this->ai->json(
            "Liigita müügikirjale saadud vastus. Kiri pakkus SEO teenust (Google'i positsiooni parandamist).\n"
            . "Kategooriad: interested (tahab rohkem infot/hinda/kohtumist), later (praegu mitte, aga hiljem võib),\n"
            . "not_interested (keeldub / palub mitte kirjutada), auto_reply (puhkuse- vms automaatvastus), unclear (ei saa aru).\n"
            . "Lisajuhised:\n" . Playbook::get('ai.reply_guidelines') . "\n\n"
            . 'Vasta JSON-ina: {"intent": "<kategooria>", "reason": "lühike põhjendus eesti keeles"}',
            "Teema: {$message->subject}\n\nVastus:\n{$body}",
            200,
        );

        $intent = $answer['intent'] ?? null;
        if (! in_array($intent, self::INTENTS, true)) {
            return ['intent' => 'unclear', 'reason' => $answer === null ? 'AI pole saadaval.' : 'AI vastus oli arusaamatu.'];
        }

        return ['intent' => $intent, 'reason' => isset($answer['reason']) ? mb_substr((string) $answer['reason'], 0, 250) : null];
    }
}
