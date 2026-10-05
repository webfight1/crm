<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\DealController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Controllers\Api\QuotationController;
use App\Http\Controllers\Api\QuotationItemController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('api.token')->group(function () {
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::get('/clients', [ClientController::class, 'index']);
    Route::get('/deals', [DealController::class, 'index']);
    Route::get('/time-entries', [TimeEntryController::class, 'index']);
    Route::get('/quotations', [QuotationController::class, 'index']);
    Route::get('/quotation-items', [QuotationItemController::class, 'index']);
});

// WuzAPI → CRM (WhatsApp messages). Auth = secret in the path.
Route::post('/chats/whatsapp/{secret}', \App\Http\Controllers\Chats\WhatsAppWebhookController::class)
    ->name('chats.whatsapp.webhook');

// tuwunel → CRM (Messenger via mautrix-meta). Auth = hs_token.
Route::prefix('/chats/matrix')->controller(\App\Http\Controllers\Chats\MatrixAppserviceController::class)->group(function () {
    Route::put('/_matrix/app/v1/transactions/{txnId}', 'transaction');
    Route::put('/transactions/{txnId}', 'transaction');
    Route::post('/_matrix/app/v1/ping', 'query');
    Route::get('/_matrix/app/v1/{kind}/{id}', 'query')->where('id', '.*');
});
