<?php

namespace App\Console\Commands;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Services\DesignAgeService;
use Illuminate\Console\Command;

/**
 * Estimates the design age of every lead's website in a campaign (via the
 * Wayback Machine) and stores the results so they can be used in emails.
 *
 * Usage:
 *   php artisan outreach:measure-design-age {campaign_id}
 *   php artisan outreach:measure-design-age {campaign_id} --force    # re-measure
 *   php artisan outreach:measure-design-age {campaign_id} --delay=2   # seconds between leads
 *
 * After running, templates can use:
 *   {{design_year}}  — e.g. 2018
 *   {{design_age}}   — e.g. 8
 *
 * Example email line:
 *   "Teie veebilehe kujundus pärineb aastast {{design_year}} — see on juba
 *    {{design_age}} aastat vana."
 */
class MeasureDesignAgeCommand extends Command
{
    protected $signature = 'outreach:measure-design-age
                            {campaign : Campaign ID}
                            {--force  : Re-measure leads that already have a design age}
                            {--delay=1 : Seconds to wait between leads (be gentle on Wayback)}';

    protected $description = 'Estimate website design age (via Wayback) for all leads in a campaign';

    public function handle(DesignAgeService $designAge): int
    {
        $campaign = OutreachCampaign::find($this->argument('campaign'));

        if (! $campaign) {
            $this->error("Campaign #{$this->argument('campaign')} not found.");
            return self::FAILURE;
        }

        $force = $this->option('force');
        $delay = max(0, (int) $this->option('delay'));

        // Load leads that have a website
        $query = $campaign->leads()->whereNotNull('website')->where('website', '!=', '');

        if (! $force) {
            $query->whereNull('design_year');
        }

        $leads = $query->get();

        if ($leads->isEmpty()) {
            $this->info($force
                ? "No leads with a website found in campaign \"{$campaign->name}\"."
                : "All leads already measured. Use --force to re-measure."
            );
            return self::SUCCESS;
        }

        $this->info('');
        $this->line("  <fg=cyan>Measuring {$leads->count()} lead(s)</> in \"{$campaign->name}\"");
        $this->line("  Source: Wayback Machine (CSS content comparison)  |  Delay between leads: {$delay}s");
        $this->info('');

        $bar = $this->output->createProgressBar($leads->count());
        $bar->setFormat(" %current%/%max% [%bar%] %percent%%  %message%");
        $bar->setMessage('Starting…');
        $bar->start();

        $results = [];
        $failed  = 0;

        foreach ($leads as $i => $lead) {
            $bar->setMessage(substr($lead->website, 0, 45));

            $data = $designAge->measure($lead);

            if ($data) {
                $results[] = [
                    $lead->company ?? $lead->email,
                    $lead->website,
                    (string) $data['design_year'],
                    "{$data['design_age']}a",
                    "{$data['similarity']}%",
                    $this->ageLabel($data['design_age']),
                ];
            } else {
                $failed++;
                $results[] = [
                    $lead->company ?? $lead->email,
                    $lead->website ?: '(no website)',
                    '<fg=gray>—</>',
                    '<fg=gray>—</>',
                    '<fg=gray>—</>',
                    '<fg=gray>unknown</>',
                ];
            }

            $bar->advance();

            // Be gentle on the Wayback Machine — sleep between leads.
            if ($delay > 0 && $i < $leads->count() - 1) {
                sleep($delay);
            }
        }

        $bar->setMessage('Done.');
        $bar->finish();

        $this->info('');
        $this->info('');

        $this->table(
            ['Company / Email', 'Website', 'Design year', 'Age', 'Similarity', 'Rating'],
            $results,
        );

        // Summary stats for measured leads
        $ages = $campaign->leads()->whereNotNull('design_age')->pluck('design_age');

        if ($ages->isNotEmpty()) {
            $this->info('');
            $this->line("  Age: avg <fg=yellow>" . round($ages->avg(), 1) . "a</> · "
                . "newest <fg=green>{$ages->min()}a</> · oldest <fg=red>{$ages->max()}a</>");
            $this->line("  Measured: {$ages->count()}  |  Failed/unknown: {$failed}");
        }

        $this->info('');
        $this->line("  Use <fg=cyan>{{design_year}}</> and <fg=cyan>{{design_age}}</> in your email templates.");
        $this->info('');

        return self::SUCCESS;
    }

    private function ageLabel(int $age): string
    {
        return match (true) {
            $age <= 2   => '<fg=green>Fresh</>',
            $age <= 5   => '<fg=yellow>Ageing</>',
            default     => '<fg=red>Dated</>',
        };
    }
}
