<?php

use App\Http\Controllers\Meriti\MeritReminderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Merit Aktiva — võlgnike meeldetuletused
|--------------------------------------------------------------------------
| Prefix /meriti, nimi meriti.*. Nõuab autenditud kasutajat (auth
| middleware pärineb web.php emagrupist). Lubatud ka outreach-only (KIND)
| režiimis — vt EnforceOutreachOnly allow-list.
*/

Route::prefix('meriti')->name('meriti.')->group(function () {
    Route::get('/', [MeritReminderController::class, 'index'])->name('index');

    Route::get('/settings', [MeritReminderController::class, 'settings'])->name('settings');
    Route::patch('/settings', [MeritReminderController::class, 'updateSettings'])->name('settings.update');

    Route::post('/send-now', [MeritReminderController::class, 'sendNow'])->name('send-now');

    Route::get('/logs', [MeritReminderController::class, 'logs'])->name('logs');
});
