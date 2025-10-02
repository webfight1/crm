<?php

namespace App\Http\Controllers;

use App\Models\TimeEntry;
use App\Models\Task;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TimeEntryController extends Controller
{
    public function edit(TimeEntry $timeEntry)
    {
        if ($timeEntry->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        return view('time-entries.edit', compact('timeEntry'));
    }

    public function update(Request $request, TimeEntry $timeEntry)
    {
        if ($timeEntry->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'hours' => 'required|integer|min:0',
            'minutes' => 'required|integer|min:0|max:59',
        ]);

        $hours = intval($validated['hours']);
        $minutes = intval($validated['minutes']);
        $duration = $hours + ($minutes / 60);
        $endTime = $timeEntry->start_time->copy()->addHours($hours)->addMinutes($minutes);

        $timeEntry->update([
            'end_time' => $endTime,
            'duration' => $duration
        ]);

        return redirect()->route('tasks.show', $timeEntry->task_id)
            ->with('success', 'Ajakanne uuendatud');
    }

    public function start(Task $task)
    {
        // Check if there's already an active timer for this user
        $activeTimer = TimeEntry::where('user_id', Auth::id())
            ->whereNull('end_time')
            ->first();

        if ($activeTimer) {
            // Stop the current timer first
            $this->stop($activeTimer);
        }

        // Start new timer
        TimeEntry::create([
            'task_id' => $task->id,
            'user_id' => Auth::id(),
            'start_time' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Timer started',
            'task' => [
                'id' => $task->id,
                'title' => $task->title,
            ]
        ]);
    }

    public function stop(TimeEntry $timeEntry)
    {
        $endTime = now();
        $startTime = $timeEntry->start_time;
        
        // Calculate duration in hours
        $duration = $startTime->diffInSeconds($endTime) / 3600;

        $timeEntry->update([
            'end_time' => $endTime,
            'duration' => $duration
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Timer stopped',
            'duration' => round($duration, 2)
        ]);
    }

    public function current(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $activeTimer = TimeEntry::with('task')
            ->where('user_id', Auth::id())
            ->whereNull('end_time')
            ->first();

        if (!$activeTimer) {
            return response()->json(['active_timer' => null]);
        }

        $duration = $activeTimer->start_time->diffInSeconds(now()) / 3600;

        return response()->json([
            'active_timer' => [
                'id' => $activeTimer->id,
                'task_id' => $activeTimer->task_id,
                'task_title' => $activeTimer->task->title,
                // Use ISO 8601 so the browser parses timezone correctly
                'start_time' => $activeTimer->start_time->toIso8601String(),
                'duration' => round($duration, 2)
            ]
        ]);
    }

    public function destroy(TimeEntry $timeEntry)
    {
        if ($timeEntry->user_id !== Auth::id()) {
            abort(403, 'Sul pole õigust seda ajakannet kustutada');
        }

        $timeEntry->delete();

        return redirect()->back()
            ->with('success', 'Ajakanne kustutatud');
    }
}
