<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\EmailCampaignController;
use App\Http\Controllers\EmailLogController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\QuotationController;

Route::get('/', function () {
    return redirect()->route('login');
});

// Public iCalendar feed (for subscriptions)
Route::get('/calendar.ics', [CalendarController::class, 'feed'])->name('calendar.ics');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');
    Route::post('register', [RegisteredUserController::class, 'store']);
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Auth routes
    Route::post('/logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Email verification routes
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');
    Route::get('verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::controller(CalendarController::class)->group(function () {
        Route::get('/calendar', 'index')->name('calendar.index');
        Route::get('/calendar/create', 'create')->name('calendar.create');
        Route::post('/calendar', 'store')->name('calendar.store');
        Route::get('/calendar/feed', 'feed')->name('calendar.feed');
    });
    
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
    
    // CRM Routes
    Route::resource('customers', CustomerController::class);
    Route::get('/customers/{customer}/details', [CustomerController::class, 'getDetails'])->name('customers.details');
    Route::resource('companies', CompanyController::class);
    
    // Get company details

    // Contacts routes
    Route::get('/companies/search/external', [CompanyController::class, 'searchExternal'])->name('companies.search.external');
    Route::resource('contacts', ContactController::class);
    Route::resource('deals', DealController::class);
    Route::get('/deals/{deal}/details', [DealController::class, 'getDetails'])->name('deals.details');
    Route::resource('tasks', TaskController::class);
    Route::resource('quotations', QuotationController::class);
    Route::post('/quotations/{quotation}/send', [QuotationController::class, 'sendByEmail'])->name('quotations.send');
    Route::get('/quotations/{quotation}/pdf', [QuotationController::class, 'downloadPdf'])->name('quotations.pdf');
    
    // Email routes

    Route::resource('email-campaigns', EmailCampaignController::class);
    Route::get('/email-campaigns/batch/{batch}', [EmailCampaignController::class, 'showBatch'])->name('email-campaigns.batch.show');
    Route::post('/email-campaigns/start-sending', [EmailCampaignController::class, 'startSending'])->name('email-campaigns.start-sending');
    Route::delete('/email-campaigns/batch/{batch}', [EmailCampaignController::class, 'destroyBatch'])->name('email-campaigns.batch.destroy');
    Route::resource('email-logs', EmailLogController::class);
    Route::get('/email-logs/cooldown-status', [EmailLogController::class, 'cooldownStatus'])->name('email-logs.cooldown-status');
    
    // Settings
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
    
    // Time entries
    Route::post('/time-entries/start/{task}', [TimeEntryController::class, 'start'])->name('time-entries.start');
    Route::post('/time-entries/stop/{timeEntry}', [TimeEntryController::class, 'stop'])->name('time-entries.stop');
    Route::get('/time-entries/current', [TimeEntryController::class, 'current'])->name('time-entries.current');
    Route::get('/time-entries/{timeEntry}/edit', [TimeEntryController::class, 'edit'])->name('time-entries.edit');
    Route::put('/time-entries/{timeEntry}', [TimeEntryController::class, 'update'])->name('time-entries.update');
    Route::delete('/time-entries/{timeEntry}', [TimeEntryController::class, 'destroy'])->name('time-entries.destroy');
    
    // Comments
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::get('/comments/{comment}/edit', [CommentController::class, 'edit'])->name('comments.edit');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{comment}/read', [CommentController::class, 'markAsRead'])->name('comments.read');
});
