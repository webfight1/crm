<?php

namespace App\Seo\Services;

use App\Models\Deal;
use App\Models\Quotation;
use App\Models\QuotationEmailSend;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Outreach\Models\OutreachSendLog;
use App\Seo\Models\SeoAudit;
use App\Seo\Models\SeoEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A client's SEO log — what happened and when, newest first.
 *
 * Changes nothing else records (SEO stage, deal stage, quotation status,
 * monitor project, notes) are written to seo_events as they happen; audits,
 * e-mails, quotations, tasks and time entries are read from their own tables.
 */
class ClientLog
{
    public const SEO_STAGES = [
        'clarify_drafted'  => 'Täpsustuskiri koostatud (mustand)',
        'awaiting_answer'  => 'Täpsustuskiri saadetud — ootab vastust',
        'answered'         => 'Klient vastas täpsustusele',
        'clarify_skipped'  => 'Täpsustus jäeti vahele',
        'access_drafted'   => 'Ligipääsukiri koostatud (mustand)',
        'access_requested' => 'Ligipääsud küsitud',
        'access_granted'   => 'Ligipääsud olemas',
    ];

    public const DEAL_STAGES = [
        'lead' => 'Potentsiaalne', 'qualified' => 'Kvalifitseeritud', 'proposal' => 'Pakkumine',
        'negotiation' => 'Läbirääkimised', 'töös' => 'Töös', 'valmis' => 'Valmis',
        'arveldatud' => 'Arveldatud', 'closed_won' => 'Võidetud', 'closed_lost' => 'Kaotatud',
        'tühistatud' => 'Tühistatud',
    ];

    public const QUOTE_STATUSES = [
        'draft' => 'mustand', 'sent' => 'saadetud', 'accepted' => 'kinnitatud',
        'rejected' => 'ei sobinud', 'expired' => 'aegus',
    ];

    /** Model hooks that write the log (AppServiceProvider::boot). */
    public static function register(): void
    {
        OutreachLead::updated(function (OutreachLead $lead) {
            if ($lead->wasChanged('seo_stage') && $lead->seo_stage) {
                self::record($lead->id, 'stage', self::SEO_STAGES[$lead->seo_stage] ?? $lead->seo_stage);
            }
            if ($lead->wasChanged('seo_monitor_project_id') && $lead->seo_monitor_project_id) {
                self::record($lead->id, 'monitor', 'Lisatud SEO-monitori (projekt #' . $lead->seo_monitor_project_id . ')');
            }
        });

        Deal::updated(function (Deal $deal) {
            if ($deal->wasChanged('stage') && ($leadId = self::leadIdForDeal($deal->id))) {
                self::record($leadId, 'deal', 'Tehing: ' . (self::DEAL_STAGES[$deal->getOriginal('stage')] ?? $deal->getOriginal('stage'))
                    . ' → ' . (self::DEAL_STAGES[$deal->stage] ?? $deal->stage), null, route('deals.show', $deal));
            }
        });

        Quotation::updated(function (Quotation $quote) {
            if ($quote->wasChanged('status') && $quote->deal_id && ($leadId = self::leadIdForDeal($quote->deal_id))) {
                self::record($leadId, 'quote', "Pakkumine {$quote->number}: " . (self::QUOTE_STATUSES[$quote->status] ?? $quote->status),
                    null, route('quotations.show', $quote));
            }
        });
    }

    /** Never breaks the action that triggered it. */
    public static function record(int $leadId, string $type, string $title, ?string $body = null, ?string $url = null, ?Carbon $at = null): void
    {
        try {
            SeoEvent::create([
                'lead_id' => $leadId, 'type' => $type, 'title' => Str::limit($title, 480),
                'body' => $body, 'url' => $url, 'user_id' => auth()->id(), 'created_at' => $at ?? now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function leadIdForDeal(int $dealId): ?int
    {
        return OutreachLead::where('deal_id', $dealId)->whereNotNull('serp_keyword')->value('id');
    }

    /**
     * @return Collection<int, array{at:Carbon, type:string, icon:string, title:string, body:?string, url:?string, who:?string}>
     */
    public function entries(OutreachLead $lead): Collection
    {
        $out = collect();
        $add = function (?Carbon $at, string $type, string $title, ?string $body = null, ?string $url = null, ?string $who = null) use ($out) {
            if ($at) {
                $out->push(['at' => $at, 'type' => $type, 'icon' => self::ICONS[$type] ?? '•', 'title' => $title, 'body' => $body, 'url' => $url, 'who' => $who]);
            }
        };

        foreach (SeoEvent::with('user:id,name')->where('lead_id', $lead->id)->get() as $e) {
            $add($e->created_at, $e->type, $e->title, $e->body, $e->url, $e->user?->name);
        }

        // Every lead row of this address — the cold-campaign one and the SEO one.
        $leadIds = OutreachLead::where('email', $lead->email)->pluck('id')->push($lead->id)->unique();
        foreach (OutreachSendLog::whereIn('lead_id', $leadIds)->where('status', 'sent')->get() as $s) {
            $add($s->sent_at, 'mail_out', 'Kampaaniakiri: ' . $s->subject);
        }
        foreach (OutreachMessage::whereIn('lead_id', $leadIds)->get() as $m) {
            $in = $m->direction === OutreachMessage::DIRECTION_INBOUND;
            $add($m->received_at ?? $m->created_at, $in ? 'mail_in' : 'mail_out',
                ($in ? 'Klient kirjutas: ' : 'Saatsin kirja: ') . ($m->subject ?: '(teemata)'),
                Str::limit(trim((string) $m->body_text), 300), OutreachMessage::inboxThreadUrl($lead->email));
        }

        foreach (SeoAudit::where('lead_id', $lead->id)->get() as $a) {
            $what = ($a->main_audit_id ? 'Lisaleht' : 'Audit') . ': ' . $a->url . ($a->keyword ? " · „{$a->keyword}“" : '');
            $url = route('seo.audits.show', $a);
            $add($a->created_at, 'audit', $what . ' — alustatud', null, $url);
            match ($a->status) {
                SeoAudit::STATUS_DONE   => $add($a->completed_at ?? $a->updated_at, 'audit', $what . " — valmis, {$a->score}/100", null, $url),
                SeoAudit::STATUS_FAILED => $add($a->completed_at ?? $a->updated_at, 'fail', $what . ' — ebaõnnestus', $a->error, $url),
                default                 => null,
            };
        }

        if ($deal = $lead->deal_id ? Deal::find($lead->deal_id) : null) {
            $add($deal->created_at, 'deal', 'Tehing loodud: ' . $deal->title, null, route('deals.show', $deal));

            $quotes = Quotation::where('deal_id', $deal->id)->get();
            foreach ($quotes as $q) {
                $add($q->created_at, 'quote', "Pakkumine {$q->number} koostatud — " . number_format((float) $q->total, 2, ',', ' ') . ' €',
                    null, route('quotations.show', $q));
            }
            foreach (QuotationEmailSend::whereIn('quotation_id', $quotes->pluck('id'))->whereNotNull('sent_at')->get() as $s) {
                $add($s->sent_at, 'mail_out', 'Pakkumine saadetud: ' . $s->to_email, $s->subject);
            }

            $tasks = Task::where('deal_id', $deal->id)->get();
            foreach ($tasks as $t) {
                $add($t->created_at, 'task', 'Ülesanne: ' . $t->title, null, route('tasks.show', $t));
                $add($t->completed_at, 'done', 'Tehtud: ' . $t->title, null, route('tasks.show', $t));
            }
            foreach (TimeEntry::with('user:id,name')->whereIn('task_id', $tasks->pluck('id'))->get() as $te) {
                $task = $tasks->firstWhere('id', $te->task_id);
                $add($te->start_time ?? $te->created_at, 'time',
                    'Tööaeg ' . self::hours((float) $te->duration) . ' — ' . $task?->title, $te->notes, $task ? route('tasks.show', $task) : null, $te->user?->name);
            }
        }

        return $out->sortByDesc(fn ($e) => $e['at']->getTimestamp())->values();
    }

    public static function hours(float $h): string
    {
        $min = (int) round($h * 60);

        return intdiv($min, 60) . ':' . str_pad((string) ($min % 60), 2, '0', STR_PAD_LEFT) . ' h';
    }

    private const ICONS = [
        'stage' => '🔀', 'deal' => '💼', 'quote' => '📄', 'monitor' => '📈', 'note' => '📝',
        'mail_in' => '📥', 'mail_out' => '📤', 'audit' => '🔍', 'fail' => '⚠️',
        'task' => '📌', 'done' => '✅', 'time' => '⏱',
    ];
}
