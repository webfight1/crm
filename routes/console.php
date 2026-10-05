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

// Every morning: monthly invoice reminders of „Püsiklient“ deals (task +
// Telegram with the RMP link); each month once, on the deal's invoice day.
Artisan::command('deals:retainer-invoices', function () {
    $this->info(app(\App\Billing\RetainerBilling::class)->run() . ' meeldetuletust.');
})->purpose('Monthly invoice reminders of retainer deals');

Schedule::command('deals:retainer-invoices')
    ->dailyAt('08:00')
    ->name('deals:retainer-invoices')
    ->withoutOverlapping();

// Every 5 minutes: AI triage of monitored WhatsApp threads with auto_ai on.
// Waits until the client has been quiet for 3 min so a burst of messages is
// read as one request; a new client wish → task + Telegram (no duplicate
// while an earlier task from the same chat is still open).
Artisan::command('chats:triage', function () {
    $triage  = app(\App\Chats\Services\ChatTriage::class);
    $factory = app(\App\Chats\Services\ChatTaskFactory::class);
    if (! $triage->enabled()) {
        return;
    }
    $ownerId = \App\Models\User::where('is_admin', true)->value('id') ?? \App\Models\User::value('id');

    $threads = \App\Chats\Models\ChatThread::where('is_monitored', true)
        ->where('auto_ai', true)
        ->where('last_message_at', '<', now()->subMinutes(3))
        ->whereHas('messages', fn ($q) => $q->where('direction', 'in')
            ->where(fn ($w) => $w->whereNull('chat_threads.triaged_at')->orWhereColumn('chat_messages.sent_at', '>', 'chat_threads.triaged_at')))
        ->get();

    foreach ($threads as $thread) {
        $ai = $triage->run($thread);
        if (! $ai || empty($ai['needs_action'])) {
            continue;
        }
        $task = $factory->openTask($thread) ?? $factory->fromTriage($thread, $ownerId);
        \App\Support\Telegram::send(
            "💬 WhatsApp — {$thread->displayName()}\n" . ($ai['summary'] ?? '')
            . ($task ? "\n\n✔ Ülesanne: {$task->title}\n" . route('tasks.show', $task) : '')
            . "\n\n" . route('chats.show', $thread)
        );
        $this->info("Triaged {$thread->displayName()}");
    }
})->purpose('AI triage of monitored WhatsApp chats (auto_ai)');

Schedule::command('chats:triage')
    ->everyFiveMinutes()
    ->name('chats:triage')
    ->withoutOverlapping(10);
