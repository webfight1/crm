<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Company;
use App\Models\Deal;
use App\Models\Task;
use App\Models\Comment;
use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Get recent comments by other users
        $recentComments = Comment::with([
            'task' => function($query) {
                $query->with('commentRead')->whereNull('deleted_at');
            },
            'user'
        ])
            ->where('user_id', '!=', auth()->id())
            ->whereHas('task', function($query) {
                $query->whereNull('deleted_at');
            })
            ->orderBy('created_at', 'desc')
            ->take(30)
            ->get();

        // Work hours (duration is decimal in hours, all users)
        $today = Carbon::today();
        $weekStart = Carbon::now()->startOfWeek();
        $monthStart = Carbon::now()->startOfMonth();

        $hoursToday = TimeEntry::whereDate('start_time', $today)
            ->whereNotNull('end_time')
            ->sum('duration');

        $hoursThisWeek = TimeEntry::where('start_time', '>=', $weekStart)
            ->whereNotNull('end_time')
            ->sum('duration');

        $hoursThisMonth = TimeEntry::where('start_time', '>=', $monthStart)
            ->whereNotNull('end_time')
            ->sum('duration');

        return view('dashboard', [
            'stats' => [
                'customers' => Customer::count(),
                'companies' => Company::count(),
                'deals' => Deal::count(),
                'tasks' => Task::count(),
                'total_deal_value' => Deal::where('stage', 'closed_won')->sum('value'),
                'won_deals' => Deal::where('stage', 'closed_won')->count(),
                'hours_today' => $hoursToday,
                'hours_this_week' => $hoursThisWeek,
                'hours_this_month' => $hoursThisMonth,
            ],
            
            'recent_customers' => Customer::latest()->take(5)->get(),
            'upcoming_tasks' => Task::with(['user', 'assignee'])
                ->where('due_date', '>=', now())
                ->orderBy('due_date', 'asc')
                ->take(15)
                ->get(),
            'recent_comments' => $recentComments,
        ]);
    }
}
