<?php

namespace App\Console\Commands;

use App\Outreach\Models\OutreachLead;
use App\Outreach\Models\OutreachMessage;
use App\Outreach\Models\OutreachSendLog;
use App\Support\Telegram;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Hourly Telegram digest: today's sends per campaign, replies and inbound mail.
 *
 * Skips quiet hours automatically — nothing is sent when there was no
 * activity in the last hour (use --force to send anyway).
 */
class OutreachTelegramSummaryCommand extends Command
{
    protected $signature = 'outreach:telegram-summary {--force : Send even if the last hour was quiet}';

    protected $description = 'Send a Telegram summary of today\'s outreach activity';

    public function handle(): int
    {
        if (! Telegram::enabled()) {
            $this->error('TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID pole seadistatud.');
            return self::FAILURE;
        }

        $today    = now()->startOfDay();
        $hourAgo  = now()->subHour();

        $sentByCampaign = OutreachSendLog::query()
            ->where('outreach_send_logs.status', OutreachSendLog::STATUS_SENT)
            ->where('outreach_send_logs.sent_at', '>=', $today)
            ->leftJoin('outreach_campaigns', 'outreach_campaigns.id', '=', 'outreach_send_logs.campaign_id')
            ->groupBy('outreach_campaigns.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->get([DB::raw("COALESCE(outreach_campaigns.name, '(kampaaniata)') as name"), DB::raw('count(*) as total')]);

        $failed = OutreachSendLog::where('status', OutreachSendLog::STATUS_FAILED)
            ->where('created_at', '>=', $today)
            ->count();

        $replies = OutreachLead::with('campaign:id,name')
            ->where('replied', true)
            ->where('replied_at', '>=', $today)
            ->orderByDesc('replied_at')
            ->get(['id', 'campaign_id', 'first_name', 'last_name', 'company', 'email', 'replied_at']);

        $inbound = OutreachMessage::where('direction', OutreachMessage::DIRECTION_INBOUND)
            ->where('received_at', '>=', $today)
            ->count();

        $activeLastHour = OutreachSendLog::where('status', OutreachSendLog::STATUS_SENT)->where('sent_at', '>=', $hourAgo)->exists()
            || OutreachMessage::where('direction', OutreachMessage::DIRECTION_INBOUND)->where('received_at', '>=', $hourAgo)->exists()
            || OutreachSendLog::where('status', OutreachSendLog::STATUS_FAILED)->where('created_at', '>=', $hourAgo)->exists();

        if (! $activeLastHour && ! $this->option('force')) {
            $this->info('Viimasel tunnil tegevust polnud — kokkuvõtet ei saadetud.');
            return self::SUCCESS;
        }

        $totalSent = $sentByCampaign->sum('total');

        $lines   = ['📊 ' . config('app.name') . ' · täna kuni ' . now()->format('H:i')];
        $lines[] = '';
        $lines[] = "✉️ Saadetud: {$totalSent}";
        foreach ($sentByCampaign as $row) {
            $lines[] = "   • {$row->name}: {$row->total}";
        }
        if ($failed > 0) {
            $lines[] = "⚠️ Ebaõnnestunud: {$failed}";
        }

        $lines[] = '';
        $lines[] = '💬 Vastanud: ' . $replies->count();
        foreach ($replies->take(8) as $lead) {
            $who = trim("{$lead->first_name} {$lead->last_name}") ?: $lead->email;
            if ($lead->company) {
                $who .= " ({$lead->company})";
            }
            $campaign = $lead->campaign?->name ? " — {$lead->campaign->name}" : '';
            $lines[]  = "   • {$lead->replied_at->format('H:i')} {$who}{$campaign}";
            $lines[]  = '     ' . OutreachMessage::inboxThreadUrl($lead->email);
        }
        if ($replies->count() > 8) {
            $lines[] = '   … ja veel ' . ($replies->count() - 8);
        }

        $lines[] = "📥 Sissetulevaid kirju kokku: {$inbound}";
        $lines[] = route('outreach.inbox.index');

        if (! Telegram::send(implode("\n", $lines))) {
            $this->error('Saatmine ebaõnnestus.');
            return self::FAILURE;
        }

        $this->info('Kokkuvõte saadetud.');
        return self::SUCCESS;
    }
}
