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

// ─── Outreach Engine Scheduler ────────────────────────────────────────────────

// Every minute: find leads ready to send and dispatch per-lead jobs
Schedule::job(new ProcessOutreachLeadsJob, 'outreach')
    ->everyMinute()
    ->name('outreach:process-leads')
    ->withoutOverlapping(5)     // Skip if previous run is still going (max 5 min overlap)
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Outreach] ProcessOutreachLeadsJob scheduled run failed.');
    });

// Every 5 minutes: check all inboxes via IMAP for lead replies
Schedule::job(new CheckOutreachRepliesJob, 'outreach')
    ->everyFiveMinutes()
    ->name('outreach:check-replies')
    ->withoutOverlapping(10)
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::error('[Outreach] CheckOutreachRepliesJob scheduled run failed.');
    });

// Every 15 minutes: scan inboxes for NDRs and mark bounced leads
Schedule::job(new CheckOutreachBouncesJob, 'outreach')
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
