<?php

namespace App\Console\Commands;

use App\Outreach\Models\OutreachCampaign;
use App\Outreach\Services\PageSpeedService;
use Illuminate\Console\Command;

/**
 * Measures Google PageSpeed scores for every lead in a campaign that
 * has a website URL and stores the results so they can be used in emails.
 *
 * Usage:
 *   php artisan outreach:measure-speed {campaign_id}
 *   php artisan outreach:measure-speed {campaign_id} --force      # re-measure already measured
 *   php artisan outreach:measure-speed {campaign_id} --delay=2    # seconds between requests
 *
 * After running, templates can use:
 *   {{performance_score}}  — e.g. 34
 *   {{lcp_mobile}}         — e.g. 4.2
 *
 * Example email line:
 *   "Teie veebileht sai Google kiirusetestis {{performance_score}}/100
 *    ja laadib mobiilil {{lcp_mobile}} sekundiga."
 */
class MeasurePageSpeedCommand extends Command
{
    protected $signature = 'outreach:measure-speed
                            {campaign : Campaign ID}
                            {--force  : Re-measure leads that already have a score}
                            {--delay=1 : Seconds to wait between requests (avoid rate limits)}';

    protected $description = 'Measure PageSpeed scores for all leads in a campaign';

    public function handle(PageSpeedService $pagespeed): int
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
            $query->whereNull('performance_score');
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
        $this->line("  Strategy: mobile  |  Delay between requests: {$delay}s");
        $this->info('');

        $bar = $this->output->createProgressBar($leads->count());
        $bar->setFormat(" %current%/%max% [%bar%] %percent%%  %message%");
        $bar->setMessage('Starting…');
        $bar->start();

        $results  = [];
        $skipped  = 0;
        $failed   = 0;

        foreach ($leads as $i => $lead) {
            $bar->setMessage(substr($lead->website, 0, 45));

            $data = $pagespeed->measure($lead);

            if ($data) {
                $results[] = [
                    $lead->company ?? $lead->email,
                    $lead->website,
                    "<fg=yellow>{$data['performance_score']}/100</>",
                    "{$data['lcp_mobile']}s",
                    $this->scoreLabel($data['performance_score']),
                ];
            } else {
                $failed++;
                $results[] = [
                    $lead->company ?? $lead->email,
                    $lead->website ?: '(no website)',
                    '<fg=gray>—</>',
                    '<fg=gray>—</>',
                    '<fg=gray>skipped</>',
                ];
            }

            $bar->advance();

            // Respect rate limits — sleep between requests (not after the last one)
            if ($delay > 0 && $i < $leads->count() - 1) {
                sleep($delay);
            }
        }

        $bar->setMessage('Done.');
        $bar->finish();

        $this->info('');
        $this->info('');

        $this->table(
            ['Company / Email', 'Website', 'Score', 'LCP mobile', 'Rating'],
            $results,
        );

        // Summary stats for measured leads
        $measured = collect($results)->filter(fn ($r) => ! str_contains($r[2], '—'));

        if ($measured->isNotEmpty()) {
            // Parse raw score values from stored leads (re-query for accuracy)
            $scores = $campaign->leads()
                ->whereNotNull('performance_score')
                ->pluck('performance_score');

            $avg = $scores->avg();
            $min = $scores->min();
            $max = $scores->max();

            $this->info('');
            $this->line("  Scores: avg <fg=yellow>{$avg}</> · min <fg=red>{$min}</> · max <fg=green>{$max}</>");
            $this->line("  Measured: {$scores->count()}  |  Failed/skipped: {$failed}");
        }

        $this->info('');
        $this->line("  Use <fg=cyan>{{performance_score}}</> and <fg=cyan>{{lcp_mobile}}</> in your email templates.");
        $this->info('');

        return self::SUCCESS;
    }

    private function scoreLabel(int $score): string
    {
        return match (true) {
            $score >= 90 => '<fg=green>Fast</>',
            $score >= 50 => '<fg=yellow>Needs work</>',
            default      => '<fg=red>Slow</>',
        };
    }
}
