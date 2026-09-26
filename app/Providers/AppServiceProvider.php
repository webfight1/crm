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

        // Work done → Telegram reminder with the link that opens the invoice
        // form in RMP for this deal's quotation (RMP makes the draft invoice).
        \App\Models\Deal::updated(function (\App\Models\Deal $deal) {
            if (! $deal->wasChanged('stage') || $deal->stage !== 'valmis') {
                return;
            }
            // „Püsiklient“ with a monthly fee: its invoices come monthly (RetainerBilling);
            // „valmis“ only ends the period.
            if ($deal->revenue_model === 'retainer' && $deal->retainer_amount > 0) {
                Telegram::send("✅ Püsikliendi töö lõpetatud (" . config('app.name') . ")\n"
                    . "{$deal->title} — kuuarvete meeldetuletused lõpevad.\nTehing: " . route('deals.show', $deal));

                return;
            }
            $quotation = \App\Models\Quotation::where('deal_id', $deal->id)
                ->whereIn('status', ['accepted', 'sent'])
                ->orderByRaw("status = 'accepted' DESC")->latest('id')->first();
            if (! $quotation) {
                return;
            }

            Telegram::send(
                "🧾 Töö valmis — tee arve (" . config('app.name') . ")\n"
                . "{$deal->title} · pakkumine {$quotation->number}, "
                . number_format((float) $quotation->total, 2, ',', ' ') . " €\n"
                . 'Loo arve RMP-s: ' . rtrim((string) config('services.rmp.url'), '/') . '/invoices/from-crm?quotation=' . urlencode($quotation->number) . "\n"
                . 'Tehing: ' . route('deals.show', $deal)
            );
        });

        // SEO: a won deal of an SEO lead → ask the client for access (task +
        // e-mail draft with hosting-specific instructions).
        \App\Models\Deal::updated(function (\App\Models\Deal $deal) {
            if (config('app.seo_pipeline') && $deal->wasChanged('stage')
                && \App\Seo\Services\AccessRequestService::isTriggerStage($deal->stage)
                && ($leadId = \App\Outreach\Models\OutreachLead::where('deal_id', $deal->id)->whereNotNull('serp_keyword')->value('id'))
            ) {
                \App\Seo\Jobs\RequestSeoAccessJob::dispatch($leadId);
            }
        });

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
