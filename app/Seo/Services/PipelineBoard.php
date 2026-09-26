<?php

namespace App\Seo\Services;

use App\Models\Deal;
use App\Models\Quotation;
use App\Models\Task;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Seo\Models\SeoAudit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Where every SEO client stands, stage by stage — the single place that
 * derives it (the /seo/kliendid page now, OpHub / a new UI later via an API).
 *
 * Stage states: done | me (waiting on the operator) | client (waiting on the
 * client) | todo (not reached yet) | fail. Each stage links to where it's handled.
 */
class PipelineBoard
{
    public const STAGES = [
        'warm'     => 'Soe klient',
        'audit'    => 'Audit',
        'clarify'  => 'Täpsustus',
        'offer'    => 'Pakkumine',
        'access'   => 'Ligipääsud',
        'monitor'  => 'SEO-monitor',
        'work'     => 'Töö',
        'invoice'  => 'Arve',
    ];

    private const CLOSED = ['closed_lost', 'tühistatud'];

    public const PROJECTS_CACHE = 'seo:board:monitor-projects';

    public function __construct(private readonly SeoMonitorClient $monitor) {}

    /** @return array<int, array{lead:OutreachLead, deal:?Deal, closed:bool, waiting:?string, stages:array<string, array>}> */
    public function rows(): array
    {
        $leads = OutreachLead::whereNotNull('serp_keyword')->whereNotNull('deal_id')
            ->latest('updated_at')->limit(200)->get();
        if ($leads->isEmpty()) {
            return [];
        }

        $deals  = Deal::whereIn('id', $leads->pluck('deal_id'))->get()->keyBy('id');
        $audits = SeoAudit::main()->whereIn('lead_id', $leads->pluck('id'))->withCount('pages')->orderBy('id')->get()->groupBy('lead_id');
        $quotes = Quotation::whereIn('deal_id', $deals->keys())->orderBy('id')->get()->groupBy('deal_id');
        $tasks  = Task::whereIn('deal_id', $deals->keys())->where('title', 'like', 'Küsi ligipääsud:%')->get()->keyBy('deal_id');
        $projects = $this->monitorProjects($leads->pluck('seo_monitor_project_id')->filter()->all());

        $rows = [];
        foreach ($leads as $lead) {
            $deal = $deals->get($lead->deal_id);
            if (! $deal) {
                continue; // deal deleted
            }
            $audit = $audits->get($lead->id)?->last();
            $quote = $quotes->get($deal->id)?->last();

            $stages = [
                'warm'    => $this->stage('done', $deal->created_at->format('d.m'), route('deals.show', $deal)),
                'audit'   => $this->auditStage($audit),
                'clarify' => $this->clarifyStage($lead, $audit),
                'offer'   => $this->offerStage($quote, $lead),
                'access'  => $this->accessStage($lead, $tasks->get($deal->id), $audit),
                'monitor' => $this->monitorStage($lead, $projects),
                'work'    => $this->workStage($deal),
                'invoice' => $this->invoiceStage($deal, $quote),
            ];

            $rows[] = [
                'lead'    => $lead,
                'deal'    => $deal,
                'closed'  => in_array($deal->stage, self::CLOSED, true),
                'done'    => $deal->stage === 'arveldatud',
                'waiting' => $this->waitingOn($stages),
                'stages'  => $stages,
            ];
        }

        return $rows;
    }

    // ─── stages ─────────────────────────────────────────────────────────────

    private function auditStage(?SeoAudit $audit): array
    {
        if (! $audit) {
            return $this->stage('todo', '');
        }
        $url = route('seo.audits.show', $audit);

        return match ($audit->status) {
            SeoAudit::STATUS_DONE   => $this->stage('done', "{$audit->score}/100" . ($audit->pages_count ? ' · +' . $audit->pages_count . ' lk' : ''), $url),
            SeoAudit::STATUS_FAILED => $this->stage('fail', 'ebaõnnestus', $url),
            default                 => $this->stage('me', 'töös…', $url),
        };
    }

    private function clarifyStage(OutreachLead $lead, ?SeoAudit $audit): array
    {
        $inbox = OutreachMessage::inboxThreadUrl($lead->email);

        return match ($lead->seo_stage) {
            'clarify_drafted' => $this->stage('me', 'saada kiri', $inbox, $lead->updated_at),
            'awaiting_answer' => $this->stage('client', 'ootab vastust', $inbox, $this->lastOutbound($lead)),
            'answered', 'access_drafted', 'access_requested' => $this->stage('done', 'vastas', $audit ? route('seo.audits.show', $audit) : $inbox),
            default => $this->stage('todo', ''),
        };
    }

    private function offerStage(?Quotation $quote, OutreachLead $lead): array
    {
        if (! $quote) {
            return $lead->seo_stage === 'answered'
                ? $this->stage('me', 'koosta', null)
                : $this->stage('todo', '');
        }
        $url = route('quotations.edit', $quote);
        $sum = number_format((float) $quote->total, 0, ',', ' ') . ' €';

        return match ($quote->status) {
            'draft'    => $this->stage('me', "{$sum} · saada", $url, $quote->created_at),
            'sent'     => $this->stage('client', "{$sum} · saadetud", $url, $quote->updated_at),
            'accepted' => $this->stage('done', $sum, $url),
            'rejected', 'expired' => $this->stage('fail', $quote->status === 'rejected' ? 'ei sobinud' : 'aegus', $url),
            default    => $this->stage('me', $sum, $url),
        };
    }

    private function accessStage(OutreachLead $lead, ?Task $task, ?SeoAudit $audit): array
    {
        $inbox = OutreachMessage::inboxThreadUrl($lead->email);
        $host = $audit?->extras['hosting']['provider'] ?? null;

        if ($task && $task->status === 'completed') {
            return $this->stage('done', $host ?? 'olemas', route('tasks.show', $task));
        }

        return match ($lead->seo_stage) {
            'access_drafted'   => $this->stage('me', 'saada kiri', $inbox, $lead->updated_at),
            'access_requested' => $this->stage('client', 'ootab' . ($host ? " ({$host})" : ''), $task ? route('tasks.show', $task) : $inbox, $this->lastOutbound($lead)),
            default            => $this->stage('todo', ''),
        };
    }

    /** @param array<int, array>|null $projects */
    private function monitorStage(OutreachLead $lead, ?array $projects): array
    {
        if (! $lead->seo_monitor_project_id) {
            return $this->stage('todo', '');
        }
        $url = $this->monitor->projectUrl($lead->seo_monitor_project_id);
        $p = $projects[$lead->seo_monitor_project_id] ?? null;

        if ($projects !== null && ! $p) {
            return $this->stage('fail', 'kustutatud', $url);
        }
        if ($p && ! ($p['google_connected'] ?? false)) {
            return $this->stage('me', 'ühenda GSC', $url . '/seaded');
        }

        return $this->stage('done', $p ? 'GSC ✓' : 'projekt', $url);
    }

    private function workStage(Deal $deal): array
    {
        return match ($deal->stage) {
            'töös'                        => $this->stage('me', 'töös', route('deals.show', $deal), $deal->updated_at),
            'valmis', 'arveldatud', 'closed_won' => $this->stage('done', 'valmis', route('deals.show', $deal)),
            default                       => $this->stage('todo', ''),
        };
    }

    private function invoiceStage(Deal $deal, ?Quotation $quote): array
    {
        if (($r = \App\Billing\RetainerBilling::status($deal)) && $r['month'] > 0 && ! in_array($deal->stage, \App\Billing\RetainerBilling::STOPPED, true)) {
            $text = $r['ended'] ? 'kuutasu lõppes' : 'kuutasu ' . $r['month'] . ($r['of'] ? "/{$r['of']}" : '');

            return $this->stage($r['ended'] ? 'me' : 'done', $text, route('deals.show', $deal));
        }

        $rmp = rtrim((string) config('services.rmp.url'), '/') . '/invoices/from-crm' . ($quote ? '?quotation=' . urlencode($quote->number) : '');

        return match ($deal->stage) {
            'valmis'     => $this->stage('me', 'tee arve', $rmp, $deal->updated_at),
            'arveldatud' => $this->stage('done', 'arveldatud', route('deals.show', $deal)),
            default      => $this->stage('todo', ''),
        };
    }

    // ─── helpers ────────────────────────────────────────────────────────────

    private function stage(string $state, string $text, ?string $url = null, ?Carbon $since = null): array
    {
        return [
            'state' => $state,
            'text'  => $text,
            'url'   => $url,
            'days'  => $since && in_array($state, ['me', 'client'], true) ? (int) $since->diffInDays(now()) : null,
        ];
    }

    /** "me" if anything waits on the operator, else "client" if anything waits on the client. */
    private function waitingOn(array $stages): ?string
    {
        $states = array_column($stages, 'state');

        return in_array('me', $states, true) ? 'me' : (in_array('client', $states, true) ? 'client' : null);
    }

    private function lastOutbound(OutreachLead $lead): ?Carbon
    {
        $at = OutreachMessage::where('direction', OutreachMessage::DIRECTION_OUTBOUND)
            ->where(fn ($q) => $q->where('lead_id', $lead->id)
                ->when($lead->customer_id, fn ($q) => $q->orWhere('customer_id', $lead->customer_id)))
            ->when($lead->seoSince(), fn ($q, $since) => $q->where('received_at', '>=', $since))
            ->max('received_at');

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * SEO-monitor projects by id (cached 5 min); null when unreachable / not set up.
     * A project missing from the cache may just be newer than it — fetched again
     * before it is shown as deleted.
     *
     * @param array<int, int> $needed project ids the rows refer to
     */
    private function monitorProjects(array $needed = []): ?array
    {
        if (! $this->monitor->enabled()) {
            return null;
        }

        $projects = $this->cachedProjects();
        if ($projects !== null && array_diff($needed, array_keys($projects))) {
            Cache::forget(self::PROJECTS_CACHE);
            $projects = $this->cachedProjects();
        }

        return $projects;
    }

    private function cachedProjects(): ?array
    {
        return Cache::remember(self::PROJECTS_CACHE, 300, function () {
            try {
                return collect($this->monitor->projects())->keyBy('id')->all();
            } catch (\Throwable) {
                return null;
            }
        });
    }
}
