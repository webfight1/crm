<?php

namespace App\Providers;

use App\Seo\Playbook;
use App\Support\Telegram;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One site cache per request / queued job, shared by the SEO services.
        $this->app->scoped(\App\Seo\Services\SiteCrawler::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Queue workers are long-lived: re-read SEO Playbook settings before
        // every job so edits on /seo/playbook apply without a worker restart.
        Queue::before(fn () => Playbook::flush());

        // Every ERROR-or-worse log line (failed sends, IMAP outages, failed
        // queue jobs, uncaught exceptions) becomes a Telegram alert, throttled
        // to one per hour per distinct message.
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            if (! in_array($event->level, ['error', 'critical', 'alert', 'emergency'], true)) {
                return;
            }

            $detail = $event->context['error']
                ?? (isset($event->context['exception']) ? $event->context['exception']->getMessage() : null);

            Telegram::sendThrottled(
                $event->message,
                "❌ " . config('app.name') . " viga\n"
                . Str::limit($event->message, 300)
                . ($detail && $detail !== $event->message ? "\n" . Str::limit((string) $detail, 500) : '')
            );
        });

        // Hourly Telegram digest. Registered here rather than in
        // routes/console.php so the same patch applies to every CRM instance.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('outreach:telegram-summary')
                ->hourly()
                ->name('outreach:telegram-summary')
                ->withoutOverlapping();
        });
    }
}
