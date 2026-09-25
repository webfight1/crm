<?php

namespace App\Seo\Jobs;

use App\Outreach\Models\OutreachLead;
use App\Seo\Models\SeoAudit;
use App\Seo\Playbook;
use App\Seo\Services\ReplyIntentService;
use App\Seo\Services\SeoAuditService;
use App\Seo\Services\SeoOfferService;
use App\Seo\Services\WarmClientService;
use App\Support\Telegram;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * The SEO pipeline after a lead replies. Each stage is switched on/off in the
 * Playbook (auto.*):
 *
 *   reply → classify intent → warm? → Customer + Deal → audit → draft quotation
 *
 * Dispatched (delayed) from OutreachLead when `replied` flips to true on a lead
 * that has a serp_keyword. Ends with one Telegram summary.
 */
class HandleSeoReplyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;
    public int $timeout = 240; // audit: page + PageSpeed (30s) + 2 LLM calls

    public function __construct(public int $leadId)
    {
        $this->onQueue('outreach');
    }

    public function handle(
        ReplyIntentService $intents,
        WarmClientService $clients,
        SeoAuditService $audits,
        SeoOfferService $offers,
    ): void {
        Playbook::flush();

        $lead = OutreachLead::with('campaign')->find($this->leadId);
        if (! $lead || ! $lead->serp_keyword) {
            return;
        }

        $steps = [];

        if (Playbook::bool('auto.classify_replies')) {
            $r = $intents->classify($lead);
            $lead->update(['reply_intent' => $r['intent'], 'reply_intent_reason' => $r['reason']]);
            $steps[] = 'Vastus: ' . ReplyIntentService::LABELS[$r['intent']] . ($r['reason'] ? " — {$r['reason']}" : '');

            if (! in_array($r['intent'], Playbook::lines('auto.warm_intents'), true)) {
                $this->notify($lead, $steps);
                return;
            }
        }

        if (! Playbook::bool('auto.create_client')) {
            $this->notify($lead, $steps);
            return;
        }

        $deal = $clients->convert($lead);
        $steps[] = "🔥 Soe klient loodud, tehing #{$deal->id}";

        if (Playbook::bool('auto.audit_on_warm') && $lead->website) {
            $audit = SeoAudit::create([
                'lead_id' => $lead->id,
                'deal_id' => $deal->id,
                'url'     => $lead->website,
                'keyword' => $lead->serp_keyword,
            ]);
            $audits->run($audit);
            $steps[] = $audit->status === SeoAudit::STATUS_DONE
                ? "Audit: skoor {$audit->score}/100, " . count($audit->failedResults()) . ' puudust'
                : "Audit ebaõnnestus: {$audit->error}";

            if ($audit->status === SeoAudit::STATUS_DONE && Playbook::bool('auto.offer_after_audit')) {
                try {
                    $q = $offers->createQuotation($audit);
                    $steps[] = "Pakkumise mustand {$q->number}: " . number_format((float) $q->total, 2, ',', ' ') . ' € — vaata üle ja saada';
                } catch (\Throwable $e) {
                    Log::warning('[SEO] offer draft failed', ['audit' => $audit->id, 'error' => $e->getMessage()]);
                    $steps[] = 'Pakkumist ei koostatud: ' . $e->getMessage();
                }
            }

            $steps[] = route('seo.audits.show', $audit);
        }

        $this->notify($lead, $steps);
    }

    private function notify(OutreachLead $lead, array $steps): void
    {
        Telegram::send(
            "🎯 SEO pipeline (" . config('app.name') . ")\n"
            . ($lead->company ?: $lead->email) . " — „{$lead->serp_keyword}“, koht {$lead->serp_position}\n"
            . implode("\n", $steps)
        );
    }
}
