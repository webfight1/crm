<?php

namespace App\Console\Commands;

use App\Outreach\Models\OutreachCampaign;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Runs a quick smoke-test against every GET-based Outreach view.
 *
 * Sends requests directly through Laravel's HTTP kernel (no web server needed).
 * Authenticates as the first user found in the database.
 *
 * Usage:
 *   php artisan outreach:smoke-test
 *   php artisan outreach:smoke-test --user=2
 *   php artisan outreach:smoke-test --slow=800    # warn if > 800 ms
 */
class OutreachSmokeTestCommand extends Command
{
    protected $signature = 'outreach:smoke-test
                            {--user=1    : User ID to authenticate as}
                            {--slow=500  : Warn threshold in milliseconds}';

    protected $description = 'Measure response time for every Outreach GET view';

    /** @var HttpKernel */
    private HttpKernel $kernel;

    public function handle(HttpKernel $kernel): int
    {
        $this->kernel = $kernel;

        $userId    = (int) $this->option('user');
        $slowLimit = (int) $this->option('slow');

        // ── Resolve a real campaign ID for routes that need one ────────────
        $campaign = OutreachCampaign::orderBy('id')->first();
        $cid      = $campaign?->id ?? 1;

        // ── Route list ─────────────────────────────────────────────────────
        $routes = [
            ['Dashboard',            '/outreach'],
            ['Accounts list',        '/outreach/accounts'],
            ['Accounts — create',    '/outreach/accounts/create'],
            ['Campaigns list',       '/outreach/campaigns'],
            ['Campaigns — create',   '/outreach/campaigns/create'],
            ['Campaign show',        "/outreach/campaigns/{$cid}"],
            ['Campaign leads',       "/outreach/campaigns/{$cid}/leads"],
            ['Send logs',            "/outreach/logs/{$cid}"],
            ['CSV template',         '/outreach/leads/csv-template'],
        ];

        $this->info('');
        $this->line("  <fg=cyan>Outreach smoke test</> — user #{$userId}, slow ≥ {$slowLimit} ms");

        if (! $campaign) {
            $this->warn("  No campaigns in DB — campaign routes will use id=1 (may 404)");
        }

        $this->info('');

        $rows    = [];
        $allOk   = true;
        $total   = 0.0;

        foreach ($routes as [$label, $uri]) {
            [$status, $ms] = $this->measure($uri, $userId);
            $total += $ms;

            $statusColor = match (true) {
                $status >= 500           => 'red',
                $status >= 400           => 'yellow',
                $status >= 300           => 'cyan',
                default                  => 'green',
            };

            $timeColor = $ms >= $slowLimit ? 'yellow' : 'green';
            $ok        = $status >= 200 && $status < 400;

            if (! $ok) {
                $allOk = false;
            }

            $icon  = $ok ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $rows[] = [
                $icon,
                $label,
                $uri,
                "<fg={$statusColor}>{$status}</>",
                "<fg={$timeColor}>{$ms} ms</>",
            ];
        }

        $this->table(
            ['', 'Page', 'URI', 'Status', 'Time'],
            $rows,
        );

        $avg = round($total / count($routes));
        $this->line("  Total: <fg=cyan>{$avg} ms avg</> across " . count($routes) . " routes.");
        $this->info('');

        if ($allOk) {
            $this->line('  <fg=green>All pages OK.</>');
        } else {
            $this->line('  <fg=red>Some pages returned errors — check above.</>');
        }

        $this->info('');

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Send a GET request through the kernel and return [statusCode, milliseconds].
     *
     * @return array{int, int}
     */
    private function measure(string $uri, int $userId): array
    {
        // Build an internal request, authenticated as the given user.
        $request = Request::create($uri, 'GET');
        $request->setLaravelSession(app('session')->driver());

        // Guard: only log in if the user actually exists.
        $user = \App\Models\User::find($userId) ?? \App\Models\User::first();
        if ($user) {
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
        }

        $start    = hrtime(true);
        $response = $this->kernel->handle($request);
        $ms       = (int) round((hrtime(true) - $start) / 1_000_000);

        $this->kernel->terminate($request, $response);

        return [$response->getStatusCode(), $ms];
    }
}
