<?php

use App\Http\Controllers\Outreach\OutreachController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Outreach Engine Routes
|--------------------------------------------------------------------------
| All routes are prefixed /outreach and named outreach.*
| Requires authenticated user (auth middleware from parent web.php include).
*/

Route::prefix('outreach')->name('outreach.')->group(function () {

    // Dashboard
    Route::get('/', [OutreachController::class, 'dashboard'])->name('dashboard');

    // ── Email Accounts ──────────────────────────────────────────────────────
    Route::prefix('accounts')->name('accounts.')->group(function () {
        Route::get('/',              [OutreachController::class, 'accountsIndex'])->name('index');
        Route::get('/create',        [OutreachController::class, 'accountsCreate'])->name('create');
        Route::post('/',             [OutreachController::class, 'accountsStore'])->name('store');
        Route::get('/{account}/edit',[OutreachController::class, 'accountsEdit'])->name('edit');
        Route::patch('/{account}',   [OutreachController::class, 'accountsUpdate'])->name('update');
        Route::delete('/{account}',  [OutreachController::class, 'accountsDestroy'])->name('destroy');
    });

    // ── Campaigns ───────────────────────────────────────────────────────────
    Route::prefix('campaigns')->name('campaigns.')->group(function () {
        Route::get('/',              [OutreachController::class, 'campaignsIndex'])->name('index');
        Route::get('/create',        [OutreachController::class, 'campaignsCreate'])->name('create');
        Route::post('/',             [OutreachController::class, 'campaignsStore'])->name('store');
        Route::get('/{campaign}',    [OutreachController::class, 'campaignsShow'])->name('show');
        Route::patch('/{campaign}',  [OutreachController::class, 'campaignsUpdate'])->name('update');
        Route::delete('/{campaign}', [OutreachController::class, 'campaignsDestroy'])->name('destroy');

        // Steps (nested under campaign)
        Route::post('/{campaign}/steps',                       [OutreachController::class, 'stepsStore'])->name('steps.store');
        Route::patch('/{campaign}/steps/{step}',               [OutreachController::class, 'stepsUpdate'])->name('steps.update');
        Route::delete('/{campaign}/steps/{step}',              [OutreachController::class, 'stepsDestroy'])->name('steps.destroy');
        Route::post('/{campaign}/steps/{step}/test-send',      [OutreachController::class, 'stepsTestSend'])->name('steps.test-send');

        // Leads (nested under campaign)
        Route::get('/{campaign}/leads',                        [OutreachController::class, 'leadsIndex'])->name('leads.index');
        Route::post('/{campaign}/leads',                       [OutreachController::class, 'leadsStore'])->name('leads.store');
        Route::patch('/{campaign}/leads/{lead}',               [OutreachController::class, 'leadsUpdate'])->name('leads.update');
        Route::delete('/{campaign}/leads/{lead}',              [OutreachController::class, 'leadsDestroy'])->name('leads.destroy');
    });

    // ── Inbox (unified replies across mailboxes) ───────────────────────────
    Route::get('/inbox',                          [OutreachController::class, 'inboxIndex'])->name('inbox.index');
    // Watched emails — operator-curated allowlist. MUST be declared before
    // the /inbox/{emailEncoded} wildcard or the wildcard swallows "watched".
    Route::post('/inbox/watched',                 [OutreachController::class, 'watchedStore'])->name('inbox.watched.store');
    Route::delete('/inbox/watched/{watched}',     [OutreachController::class, 'watchedDestroy'])->name('inbox.watched.destroy');
    Route::get('/inbox/{emailEncoded}',           [OutreachController::class, 'inboxThread'])->name('inbox.thread');
    Route::post('/inbox/{emailEncoded}/reply',    [OutreachController::class, 'inboxReply'])->name('inbox.reply');
    Route::post('/inbox/{emailEncoded}/contact',  [OutreachController::class, 'inboxUpdateContact'])->name('inbox.contact');
    Route::post('/inbox/{emailEncoded}/archive',  [OutreachController::class, 'inboxArchive'])->name('inbox.archive');
    Route::post('/inbox/{emailEncoded}/unarchive',[OutreachController::class, 'inboxUnarchive'])->name('inbox.unarchive');

    // ── Logs ────────────────────────────────────────────────────────────────
    Route::get('/logs/{campaign}', [OutreachController::class, 'logsIndex'])->name('logs.index');

    // ── CSV Import ───────────────────────────────────────────────────────────
    Route::post('/leads/import', [OutreachController::class, 'importCsv'])->name('leads.import');
    Route::get('/leads/csv-template', [OutreachController::class, 'csvTemplate'])->name('leads.csv-template');

    // ── Manual Triggers ─────────────────────────────────────────────────────
    Route::post('/trigger/process',     [OutreachController::class, 'triggerProcess'])->name('trigger.process');
    Route::post('/trigger/reply-check', [OutreachController::class, 'triggerReplyCheck'])->name('trigger.reply-check');
});
