<?php

namespace App\Providers;

use App\Support\Telegram;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
