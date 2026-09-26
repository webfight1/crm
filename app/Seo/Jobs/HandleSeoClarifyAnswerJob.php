<?php

namespace App\Seo\Jobs;

use App\Outreach\Models\OutreachLead;
use App\Seo\Models\SeoAudit;
use App\Seo\Playbook;
use App\Seo\Services\ClarifyService;
use App\Seo\Services\LandingPageFinder;
use App\Seo\Services\ReplyIntentService;
use App\Seo\Services\SeoAuditService;
use App\Seo\Services\SeoOfferService;
use App\Support\Telegram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * The client answered our clarification e-mail (seo_stage = awaiting_answer).
 *
 *   - named another page on their site → audit that page (page_source client)
 *   - confirmed our page               → mark the audit client-confirmed
 *   - named more keywords              → look for each one's page, store on the
 *                                        lead as "keyword | url" (empty = no page yet)
 *   - clarify.wait_for_answer          → now draft the quotation
 *
 * Dispatched (delayed) from OutreachMessage when the answer arrives.
 */
class HandleSeoClarifyAnswerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 300; // re-audit + finder for up to 5 keywords

    public function __construct(public int $leadId)
    {
        $this->onQueue('outreach');
    }

    public function handle(
        ClarifyService $clarify,
        SeoAuditService $audits,
        SeoOfferService $offers,
        LandingPageFinder $finder,
    ): void {
        $lead = OutreachLead::find($this->leadId);
        if (! $lead || $lead->seo_stage !== 'awaiting_answer') {
            return;
        }
        $message = ReplyIntentService::latestReply($lead);
        if (! $message) {
            return;
        }
        $lead->update(['seo_stage' => 'answered']);

        $audit = SeoAudit::where('lead_id', $lead->id)->latest('id')->first();
        $proposed = $audit && in_array($audit->page_source, ['csv', 'found', 'home'], true) ? $audit->url : null;

        $a = $clarify->parseAnswer($lead, ReplyIntentService::replyText($message), $proposed);
        $steps = $a['summary'] ? ["Vastus: {$a['summary']}"] : [];

        // Page
        if ($a['url']) {
            $audit = SeoAudit::create([
                'lead_id'     => $lead->id,
                'deal_id'     => $lead->deal_id ?? $audit?->deal_id,
                'url'         => $a['url'],
                'keyword'     => $lead->serp_keyword,
                'page_source' => 'client',
                'page_note'   => 'Klient nimetas selle lehe täpsustuskirja vastuses.',
            ]);
            $audits->run($audit);
            $steps[] = "Klient nimetas lehe {$a['url']} → uus audit: "
                . ($audit->status === SeoAudit::STATUS_DONE ? "skoor {$audit->score}/100" : "ebaõnnestus ({$audit->error})");
        } elseif ($a['confirmed'] === true && $audit && $proposed) {
            $audit->update(['page_source' => 'client', 'page_note' => 'Klient kinnitas lehe täpsustuskirja vastuses.']);
            $steps[] = 'Klient kinnitas lehe ✔';
        } elseif ($a['confirmed'] === false) {
            $steps[] = '⚠️ Klient ei kinnitanud lehte, aga linki ei andnud — vaata vastust.';
        }

        // Extra keywords
        if ($a['keywords']) {
            $site = $lead->website ?: $audit?->url;
            $lines = [];
            foreach ($a['keywords'] as $kw) {
                $page = $site ? $finder->find($site, $kw) : ['url' => null];
                $lines[] = $kw . ' | ' . ($page['url'] ?? '');
            }
            $lead->update(['seo_extra_keywords' => implode("\n", $lines)]);
            $steps[] = "Lisamärksõnad:\n" . implode("\n", array_map(
                fn ($l) => '• ' . str_replace(' | ', ' → ', $l) . (str_ends_with($l, '| ') ? 'oma leht puudub' : ''),
                $lines
            ));
        }

        // Offer
        if ($audit && $audit->status === SeoAudit::STATUS_DONE && ! $audit->quotation_id
            && Playbook::bool('clarify.wait_for_answer') && Playbook::bool('auto.offer_after_audit')
        ) {
            try {
                $q = $offers->createQuotation($audit);
                $steps[] = "Pakkumise mustand {$q->number}: " . number_format((float) $q->total, 2, ',', ' ') . ' € — vaata üle ja saada: '
                        . route('quotations.edit', $q);
                    $steps[] = 'Kui klient on nõus, liiguta tehing etappi „töös“ (käivitab ligipääsukirja + SEO-monitori): '
                        . route('deals.show', $q->deal_id);
            } catch (\Throwable $e) {
                Log::warning('[SEO] offer draft failed', ['audit' => $audit->id, 'error' => $e->getMessage()]);
                $steps[] = 'Pakkumist ei koostatud: ' . $e->getMessage();
            }
        }

        if ($audit) {
            $steps[] = route('seo.audits.show', $audit);
        }

        Telegram::send(
            "🎯 SEO täpsustus (" . config('app.name') . ")\n"
            . ($lead->company ?: $lead->email) . " — „{$lead->serp_keyword}“\n"
            . implode("\n", $steps ?: ['Vastusest ei leitud midagi konkreetset — vaata postkasti.'])
        );
    }
}
