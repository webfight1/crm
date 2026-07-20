<?php

namespace App\Console\Commands;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Services\MxCheckService;
use Illuminate\Console\Command;

/**
 * Runs MX validation across an outreach campaign's leads (or all leads
 * without a stored mx result when no campaign is given).
 *
 * Usage:
 *   php artisan outreach:check-mx {campaign?}   check leads in the campaign
 *   php artisan outreach:check-mx --force       re-check leads that already have a result
 *
 * Populates outreach_leads.mx_ok + .mx_checked_at. The send-guard in
 * OutreachEmailService::passesSendGuards() blocks sends when mx_ok=false,
 * so leads with dead domains stop consuming worker cycles without the
 * operator having to manually clean them up.
 */
class CheckLeadMxCommand extends Command
{
    protected $signature = 'outreach:check-mx
                            {campaign? : Campaign ID (default: all campaigns)}
                            {--force : Re-check leads whose mx_ok is already set}';

    protected $description = 'Verify each lead\'s domain has a working MX record';

    public function handle(MxCheckService $mx): int
    {
        $query = OutreachLead::query()->whereNotNull('email')->where('email', '!=', '');

        if ($id = $this->argument('campaign')) {
            $campaign = OutreachCampaign::find($id);
            if (! $campaign) {
                $this->error("Kampaania #{$id} ei leitud.");
                return self::FAILURE;
            }
            $query->where('campaign_id', $campaign->id);
        }

        if (! $this->option('force')) {
            $query->whereNull('mx_ok');
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('Kontrollimist vajavaid lead\'e pole.');
            return self::SUCCESS;
        }

        $this->info("Kontrollin {$total} lead(i)...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok = $bad = 0;
        $query->chunkById(500, function ($leads) use ($mx, $bar, &$ok, &$bad) {
            foreach ($leads as $lead) {
                $result = $mx->hasMx($lead->email);
                $lead->forceFill([
                    'mx_ok'         => $result,
                    'mx_checked_at' => now(),
                ])->saveQuietly();
                $result ? $ok++ : $bad++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Kontroll valmis: {$ok} korrektset, {$bad} vigast (dead domain / no MX).");

        if ($bad > 0) {
            $this->comment('Vigased lead\'id: SendJob-i väljumis-kaitse jätab need saatmisest välja automaatselt.');
        }

        return self::SUCCESS;
    }
}
