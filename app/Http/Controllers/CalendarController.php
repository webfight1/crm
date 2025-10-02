<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CalendarController extends Controller
{
    public function index()
    {
        $events = CalendarEvent::with(['user', 'task'])
            ->where('start_time', '>=', now()->startOfMonth())
            ->where('start_time', '<=', now()->endOfMonth())
            ->get();

        return view('calendar.index', compact('events'));
    }

    public function create()
    {
        $users = User::all();
        $tasks = Task::where('status', '!=', 'completed')->get();

        return view('calendar.create', compact('users', 'tasks'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'location' => 'nullable|string|max:255',
            'type' => 'required|in:meeting,call,other',
            'task_id' => 'nullable|exists:tasks,id',
            'attendees' => 'nullable|array',
            'attendees.*' => 'exists:users,id',
            'status' => 'required|in:confirmed,cancelled,tentative'
        ]);

        $validated['user_id'] = Auth::id();
        $validated['uuid'] = (string) Str::uuid();

        CalendarEvent::create($validated);

        return redirect()->route('calendar.index')
            ->with('success', 'Event created successfully.');
    }

    public function feed()
    {
        $events = CalendarEvent::where('start_time', '>=', now()->subDays(30))
            ->where('status', '!=', 'cancelled')
            ->get();

        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//CRM//Calendar//ET\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";

        foreach ($events as $event) {
            $ical .= "BEGIN:VEVENT\r\n";
            $ical .= "UID:" . ($event->uuid ?? Str::uuid()) . "\r\n";
            $ical .= "SUMMARY:" . $this->escapeString($event->title) . "\r\n";
            if ($event->description) {
                $ical .= "DESCRIPTION:" . $this->escapeString($event->description) . "\r\n";
            }
            $ical .= "DTSTART:" . $event->start_time->format('Ymd\\THis\\Z') . "\r\n";
            $ical .= "DTEND:" . $event->end_time->format('Ymd\\THis\\Z') . "\r\n";
            if ($event->location) {
                $ical .= "LOCATION:" . $this->escapeString($event->location) . "\r\n";
            }
            $ical .= "END:VEVENT\r\n";
        }

        $ical .= "END:VCALENDAR";

        return response($ical)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="calendar.ics"');
    }

    private function escapeString($string): string
    {
        $string = str_replace('\\', '\\\\', $string);
        $string = str_replace("\n", '\\n', $string);
        $string = str_replace(",", '\\,', $string);
        $string = str_replace(";", '\\;', $string);
        return $string;
    }
}
