<?php

use App\Outreach\Jobs\CheckOutreachBouncesJob;
use App\Outreach\Jobs\CheckOutreachRepliesJob;
use App\Outreach\Jobs\ProcessOutreachLeadsJob;
use App\Outreach\Jobs\ResetOutreachDailyLimitsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('telegram:test', function () {
    if (! \App\Support\Telegram::enabled()) {
        $this->error('TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID pole seadistatud.');
        return 1;
    }
    if (! \App\Support\Telegram::send('✅ CRM testteade ' . now()->format('d.m.Y H:i'))) {
        $this->error('Saatmine ebaõnnestus (vale token/chat_id või võrguviga).');
        return 1;
    }
    $this->info('Testteade saadetud.');
})->purpose('Send a test message to the Telegram alert chat');

// ─── Outreach Engine Scheduler ────────────────────────────────────────────────

// Every minute: find leads ready to send and dispatch per-lead jobs
Schedule::job(new ProcessOutreachLeadsJob, 'outreach')
    ->everyMinute()
    ->name('outreach:process-leads')
    ->withoutOverlapping(5)     // Skip if previous run is still going (max 5 min overlap)
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Outreach] ProcessOutreachLeadsJob scheduled run failed.');
    });

// Every 5 minutes: check all inboxes via IMAP for lead replies.
// IMAP jobs run on the outreach-inbox worker: a slow/broken mailbox can take
// minutes and must not block SendOutreachEmailJob on the 'outreach' queue.
Schedule::job(new CheckOutreachRepliesJob, 'outreach-inbox')
    ->everyFiveMinutes()
    ->name('outreach:check-replies')
    ->withoutOverlapping(10)
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Outreach] CheckOutreachRepliesJob scheduled run failed.');
    });

// Every 15 minutes: scan inboxes for NDRs and mark bounced leads
Schedule::job(new CheckOutreachBouncesJob, 'outreach-inbox')
    ->everyFifteenMinutes()
    ->name('outreach:check-bounces')
    ->withoutOverlapping(10)
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Outreach] CheckOutreachBouncesJob scheduled run failed.');
    });

// Daily at midnight: reset sent_today counter on all inboxes
Schedule::job(new ResetOutreachDailyLimitsJob, 'outreach')
    ->dailyAt('00:00')
    ->name('outreach:reset-daily-limits')
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Outreach] ResetOutreachDailyLimitsJob scheduled run failed.');
    });

// Every minute: dispatch any inbox replies that the operator scheduled
// for a future send time and whose moment has now arrived.
Schedule::command('outreach:send-scheduled-replies')
    ->everyMinute()
    ->name('outreach:send-scheduled-replies')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Outreach] send-scheduled-replies run failed.');
    });
