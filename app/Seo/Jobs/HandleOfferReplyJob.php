<?php

namespace App\Seo\Jobs;

use App\Models\Quotation;
use App\Outreach\Models\OutreachMessage;
use App\Seo\Services\ReplyIntentService;
use App\Seo\Services\SeoAi;
use App\Support\Telegram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * A client answered a quotation e-mail ("Re: Pakkumine #Q2026018"). The AI
 * reads the answer — accepted / questions / rejected / unclear — and Telegram
 * gets the verdict with the client's words and a link to the deal, where the
 * operator moves it to „töös“ (that starts the access request + SEO-monitor).
 * An accepted answer also marks a sent quotation "accepted"; the deal stage is
 * never changed here.
 *
 * Dispatched (delayed) from OutreachMessage for inbound mail whose subject
 * carries a quotation number.
 */
class HandleOfferReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const VERDICTS = [
        'accepted'  => '✅ Sobib',
        'questions' => '❓ Küsimused',
        'rejected'  => '❌ Ei sobi',
        'unclear'   => '🤔 Ebaselge',
    ];

    public int $tries   = 1;
    public int $timeout = 90;

    public function __construct(public int $messageId)
    {
        $this->onQueue('outreach');
    }

    /** Quotation number in a subject line, e.g. "Re: Pakkumine #Q2026018". */
    public static function quotationNumber(?string $subject): ?string
    {
        return preg_match('/\b(Q\d{6,})\b/u', (string) $subject, $m) ? $m[1] : null;
    }

    public function handle(SeoAi $ai): void
    {
        $message = OutreachMessage::find($this->messageId);
        $number = $message ? self::quotationNumber($message->subject) : null;
        $quotation = $number ? Quotation::with('deal')->where('number', $number)->first() : null;
        if (! $quotation) {
            return;
        }

        $text = ReplyIntentService::replyText($message);
        $answer = $ai->json(
            "Klient vastas hinnapakkumisele. Otsusta, mida ta vastas.\n"
            . "accepted = nõustub / tellib / „sobib“, „paneme käima“; questions = küsib täpsustusi, tahab muudatusi või kohtumist;\n"
            . "rejected = loobub; unclear = ei saa aru.\n"
            . 'Vasta JSON-ina: {"verdict": "accepted|questions|rejected|unclear", "reason": "lühike kokkuvõte eesti keeles"}',
            "Pakkumine {$quotation->number}, summa " . number_format((float) $quotation->total, 2, ',', ' ') . " €\n\nVastus:\n{$text}",
            200,
        );
        $verdict = array_key_exists($answer['verdict'] ?? '', self::VERDICTS) ? $answer['verdict'] : 'unclear';
        $reason = is_string($answer['reason'] ?? null) ? mb_substr($answer['reason'], 0, 200) : null;

        if ($verdict === 'accepted' && $quotation->status === 'sent') {
            $quotation->update(['status' => 'accepted']);
        }

        $deal = $quotation->deal;
        $lines = [
            "💶 Vastus pakkumisele {$quotation->number}" . ($deal ? " — {$deal->title}" : ''),
            self::VERDICTS[$verdict] . ($reason ? ": {$reason}" : ''),
            '„' . mb_substr(trim(preg_replace('/\s+/u', ' ', $text)), 0, 200) . '“',
        ];
        if ($deal && $verdict === 'accepted') {
            $lines[] = 'Liiguta tehing etappi „töös“ (käivitab ligipääsukirja + SEO-monitori): ' . route('deals.show', $deal);
        } elseif ($deal) {
            $lines[] = 'Tehing: ' . route('deals.show', $deal);
        }
        if ($verdict !== 'accepted') {
            $lines[] = 'Vasta: ' . OutreachMessage::inboxThreadUrl($message->from_email);
        }

        Telegram::send(implode("\n", $lines));
    }
}
