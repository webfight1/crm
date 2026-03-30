<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|in:pending,in_progress,needs_testing,needs_clarification,completed,cancelled',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'messages' => $validator->errors()
            ], 422);
        }

        $query = DB::table('tasks');

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Apply limit
        $limit = $request->input('limit', 100);
        $query->limit($limit);

        // Order by created_at descending
        $query->orderBy('created_at', 'desc');

        $tasks = $query->get();

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'count' => $tasks->count()
        ]);
    }
}
