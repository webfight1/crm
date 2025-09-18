<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DealController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $user = Auth::user();
    
    $stats = [
        'customers' => \App\Models\Customer::where('user_id', $user->id)->count(),
        'companies' => \App\Models\Company::where('user_id', $user->id)->count(),
        'deals' => \App\Models\Deal::where('user_id', $user->id)->count(),
        'tasks' => \App\Models\Task::where('user_id', $user->id)->where('status', '!=', 'completed')->count(),
        'total_deal_value' => \App\Models\Deal::where('user_id', $user->id)->where('stage', '!=', 'closed_lost')->sum('value'),
        'won_deals' => \App\Models\Deal::where('user_id', $user->id)->where('stage', 'closed_won')->count(),
    ];
    
    $recent_customers = \App\Models\Customer::where('user_id', $user->id)
        ->with('company')
        ->latest()
        ->take(5)
        ->get();
        
    $upcoming_tasks = \App\Models\Task::where('user_id', $user->id)
        ->where('status', '!=', 'completed')
        ->with(['customer', 'company', 'deal'])
        ->orderBy('due_date', 'asc')
        ->take(5)
        ->get();
    
    return view('dashboard', compact('stats', 'recent_customers', 'upcoming_tasks'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // CRM Routes
    Route::resource('customers', CustomerController::class);
    Route::resource('companies', CompanyController::class);
    Route::resource('contacts', ContactController::class);
    Route::resource('deals', DealController::class);
    Route::resource('tasks', TaskController::class);
});

require __DIR__.'/auth.php';
