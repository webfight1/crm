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
