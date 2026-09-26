<?php

namespace App\Seo\Services;

use App\Models\Deal;
use App\Models\Task;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Seo\Models\SeoAudit;
use App\Seo\Playbook;
use App\Support\Telegram;

/**
 * Step 3 of the access ladder: the deal is won → ask for real access.
 * Creates a "Küsi ligipääsud" task and an e-mail draft (pre-filled in the
 * inbox reply form) with the instructions for the client's host — SSH on
 * Zone/Veebimajutus/Radicenter, an admin invite on Voog/Wix/Shopify — plus
 * Search Console „Täielik“. Also creates the client's SEO-monitor project
 * (SeoMonitorSyncService) and puts the client's password link in the draft.
 * Never sends anything.
 */
class AccessRequestService
{
    public function __construct(
        private readonly HostingDetector $hosting,
        private readonly SeoMonitorSyncService $monitor,
    ) {}

    /** Deal stages (Playbook access.trigger_stages) that start the request. */
    public static function isTriggerStage(?string $stage): bool
    {
        $stages = array_filter(array_map('trim', explode(',', mb_strtolower(Playbook::get('access.trigger_stages')))));

        return $stage !== null && in_array(mb_strtolower($stage), $stages, true);
    }

    /** @return bool false when already requested for this lead */
    public function request(OutreachLead $lead, ?int $userId = null): bool
    {
        if (in_array($lead->seo_stage, ['access_drafted', 'access_requested'], true)) {
            return false;
        }

        $audit = SeoAudit::main()->where('lead_id', $lead->id)->latest('id')->first();
        $hosting = $audit->extras['hosting'] ?? null;
        if (! $hosting && ($lead->website || $audit)) {
            $hosting = $this->hosting->detect($lead->website ?: $audit->url);
            if ($audit) {
                $audit->update(['extras' => array_merge($audit->extras ?? [], ['hosting' => $hosting])]);
            }
        }

        try {
            $monitor = $this->monitor->sync($lead);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[SEO] SEO-monitor sync failed', ['lead' => $lead->id, 'error' => $e->getMessage()]);
            $monitor = ['project_url' => null, 'invite_url' => null, 'note' => 'SEO-monitori projekti loomine ebaõnnestus: ' . mb_substr($e->getMessage(), 0, 200)];
        }

        $lead->update([
            'seo_stage'       => 'access_drafted',
            'seo_access_body' => $this->draft($lead, $hosting, $monitor['invite_url']),
        ]);
        $monitorLines = array_filter([
            $monitor['note'],
            $monitor['project_url'] ? 'Ühenda Search Console SEO-monitoris (projekti seaded), kui klient on ligipääsu andnud: ' . $monitor['project_url'] : null,
        ]);

        $deal = $lead->deal_id ? Deal::find($lead->deal_id) : null;
        $owner = $userId ?? $deal?->user_id ?? \App\Models\User::orderBy('id')->value('id');
        Task::create([
            'title'       => 'Küsi ligipääsud: ' . ($lead->company ?: $lead->website ?: $lead->email),
            'description' => 'Kirja mustand on postkastis: ' . OutreachMessage::inboxThreadUrl($lead->email) . "\n"
                . ($hosting['note'] ?? 'Majutaja teadmata.') . ($monitorLines ? "\n" . implode("\n", $monitorLines) : ''),
            'type'        => 'email',
            'priority'    => 'high',
            'status'      => 'pending',
            'due_date'    => now()->addDay(),
            'customer_id' => $lead->customer_id,
            'company_id'  => $deal?->company_id,
            'deal_id'     => $deal?->id,
            'user_id'     => $owner,
            'assignee_id' => $owner,
            'price'       => 0,
        ]);

        Telegram::send(
            "🔑 SEO ligipääsud (" . config('app.name') . ")\n"
            . ($lead->company ?: $lead->email) . "\n"
            . ($hosting['note'] ?? 'Majutaja teadmata.') . "\n"
            . ($monitorLines ? implode("\n", $monitorLines) . "\n" : '')
            . 'Kirja mustand postkastis: ' . OutreachMessage::inboxThreadUrl($lead->email)
            . ($deal ? "\nTehing: " . route('deals.show', $deal) : '')
        );

        return true;
    }

    /** HTML body for the inbox reply editor. */
    public function draft(OutreachLead $lead, ?array $hosting, ?string $inviteUrl = null): string
    {
        $myEmail = trim(Playbook::get('clarify.gsc_email'));
        $sshKey  = trim(Playbook::get('access.ssh_key'));
        $provider = $hosting['provider'] ?? null;

        $vars = [
            '{{name}}'     => $lead->first_name && $lead->first_name !== 'Friend' ? ', ' . $lead->first_name : '',
            '{{company}}'  => (string) $lead->company,
            '{{website}}'  => (string) $lead->website,
            '{{provider}}' => (string) ($provider ?: 'majutaja'),
            '{{my_email}}' => $myEmail !== '' ? $myEmail : 'minu e-posti',
            '{{ssh_key}}'  => $sshKey !== '' ? $sshKey : '(saadan võtme eraldi)',
        ];

        $block = ($hosting['kind'] ?? null) === 'platform' ? 'Platvorm' : $provider;
        $vars['{{instructions}}'] = strtr($this->instructions($block), $vars);
        $vars['{{gsc_request}}'] = $myEmail !== '' ? strtr(Playbook::get('access.gsc_text'), $vars) : '';
        $vars['{{monitor_request}}'] = $inviteUrl
            ? strtr(Playbook::get('access.monitor_text'), ['{{monitor_link}}' => $inviteUrl])
            : '';

        // A body saved before {{monitor_request}} existed gets it appended.
        $body = Playbook::get('access.email');
        if ($vars['{{monitor_request}}'] !== '' && ! str_contains($body, '{{monitor_request}}')) {
            $body .= "\n\n{{monitor_request}}";
        }
        $text = strtr($body, $vars);
        $paragraphs = array_filter(array_map('trim', preg_split('/\R{2,}/u', trim($text)) ?: []), 'strlen');

        // Numbered items ("1. …") are renumbered — optional ones may be missing.
        $n = 0;
        foreach ($paragraphs as &$p) {
            if (preg_match('/^\d+\.\s/u', $p)) {
                $p = preg_replace('/^\d+\./u', ++$n . '.', $p);
            }
        }
        unset($p);

        return implode("\n", array_map(function ($p) {
            $html = nl2br(e($p), false);
            return '<p>' . preg_replace('~(https?://[^\s<]+[^\s<.,;:!?)])~u', '<a href="$1">$1</a>', $html) . '</p>';
        }, $paragraphs));
    }

    /** The "## <name>" block of access.instructions, else "## Muu". */
    private function instructions(?string $name): string
    {
        $blocks = [];
        $current = null;
        foreach (preg_split('/\R/u', Playbook::get('access.instructions')) ?: [] as $line) {
            if (preg_match('/^##\s*(.+?)\s*$/u', $line, $m)) {
                $current = mb_strtolower($m[1]);
                $blocks[$current] = '';
                continue;
            }
            if ($current !== null) {
                $blocks[$current] .= $line . "\n";
            }
        }

        return trim($blocks[mb_strtolower((string) $name)] ?? $blocks['muu'] ?? '');
    }
}
