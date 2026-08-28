<?php

namespace App\Console\Commands;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Models\OutreachLead;
use App\Outreach\Services\OutreachDraftGeneratorService;
use Illuminate\Console\Command;

/**
 * Batch-generate AI drafts for a campaign's leads.
 *
 * Usage:
 *   php artisan outreach:generate-drafts 8               # all leads without a draft
 *   php artisan outreach:generate-drafts 8 --limit=50    # cap this run
 *   php artisan outreach:generate-drafts 8 --force       # re-generate leads that already have drafts
 *   php artisan outreach:generate-drafts --lead=1234     # single lead by id
 *   php artisan outreach:generate-drafts 8 --delay=1     # seconds between API calls
 *
 * By default we skip leads whose outreach_generation_status is 'ready'
 * or 'approved' — those already have reviewable output. 'failed' rows
 * ARE retried automatically since a transient error shouldn't strand
 * a lead. --force wipes that guard and re-generates everything.
 */
class GenerateOutreachDraftsCommand extends Command
{
    protected $signature = 'outreach:generate-drafts
                            {campaign? : Campaign ID (required unless --lead is given)}
                            {--limit=25 : Max leads to process this run}
                            {--force : Re-generate leads that already have a ready/approved draft}
                            {--lead= : Generate for one specific lead ID (bypasses campaign)}
                            {--delay=1 : Seconds between OpenAI calls (rate-limit cushion)}';

    protected $description = 'Generate personalised AI cold-email drafts for outreach leads';

    public function handle(OutreachDraftGeneratorService $gen): int
    {
        // Single-lead mode: quick regenerate from anywhere.
        if ($id = $this->option('lead')) {
            $lead = OutreachLead::find($id);
            if (! $lead) { $this->error("Lead #{$id} not found."); return self::FAILURE; }

            $this->info("Generating draft for lead #{$lead->id} ({$lead->email})...");
            $ok = $gen->generate($lead);
            $this->line($ok ? '✅ ready — awaiting operator review' : '❌ failed — see outreach_generation_error');
            return $ok ? self::SUCCESS : self::FAILURE;
        }

        $campaignId = $this->argument('campaign');
        if (! $campaignId) {
            $this->error('Kampaania ID puudub (või anna --lead=<id>).');
            return self::FAILURE;
        }

        $campaign = OutreachCampaign::find($campaignId);
        if (! $campaign) { $this->error("Campaign #{$campaignId} not found."); return self::FAILURE; }

        $query = $campaign->leads()->whereNotNull('website')->where('website', '!=', '');
        if (! $this->option('force')) {
            // Skip already-good drafts; retry 'pending' (interrupted runs) and
            // 'failed' (transient errors deserve a second chance).
            $query->where(function ($q) {
                $q->whereNull('outreach_generation_status')
                  ->orWhereIn('outreach_generation_status', [OutreachLead::DRAFT_PENDING, OutreachLead::DRAFT_FAILED]);
            });
        }

        $limit = max(1, (int) $this->option('limit'));
        $leads = $query->orderBy('id')->limit($limit)->get();

        if ($leads->isEmpty()) {
            $this->info('Genereerimist vajavaid lead\'e pole. Kasuta --force uuesti genereerimiseks.');
            return self::SUCCESS;
        }

        $this->info("Genereerin {$leads->count()} lead(i) kampaanias \"{$campaign->name}\"...");
        $bar = $this->output->createProgressBar($leads->count());
        $bar->start();

        $ok = $bad = 0;
        $delayMs = (int) round(((float) $this->option('delay')) * 1000);

        foreach ($leads as $lead) {
            $gen->generate($lead) ? $ok++ : $bad++;
            $bar->advance();
            if ($delayMs > 0) usleep($delayMs * 1000);
        }
        $bar->finish();
        $this->newLine(2);

        $this->info("Valmis: {$ok} õnnestunud, {$bad} ebaõnnestunud.");
        if ($bad > 0) {
            $this->comment('Ebaõnnestunud lead\'ide vead: outreach_generation_error veerus. UI-s kuvatakse punase märgiga.');
        }

        return self::SUCCESS;
    }
}
