<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TimeEntryController extends Controller
{
    /**
     * Display a listing of time entries.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'task_id' => 'nullable|integer|exists:tasks,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = DB::table('time_entries');

        // Apply task_id filter
        if ($request->has('task_id')) {
            $query->where('task_id', $request->task_id);
        }

        // Apply user_id filter
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Apply date range filter
        if ($request->has('date_from')) {
            $query->whereDate('start_time', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('start_time', '<=', $request->date_to);
        }

        // Apply limit
        $limit = $request->input('limit', 100);
        $query->limit($limit);

        // Order by start_time descending
        $query->orderBy('start_time', 'desc');

        $timeEntries = $query->get();

        // Calculate total duration
        $totalDuration = $timeEntries->sum('duration');

        return response()->json([
            'success' => true,
            'data' => $timeEntries,
            'count' => $timeEntries->count(),
            'total_duration' => $totalDuration
        ]);
    }
}
