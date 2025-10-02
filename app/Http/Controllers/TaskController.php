<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Customer;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $query = Task::with(['customer', 'company', 'contact', 'deal', 'user', 'assignee']);

        // Kasutaja filter
        if ($request->has('user_id') && $request->user_id !== 'all') {
            if ($request->user_id === 'mine') {
                $query->where('user_id', Auth::id());
            } elseif ($request->user_id === 'assigned') {
                $query->where('assignee_id', Auth::id());
            } else {
                $query->where('user_id', $request->user_id);
            }
        }

        $tasks = $query->orderBy('due_date', 'asc')
                ->paginate(15)
                ->withQueryString();

        // Hangi kõik kasutajad filtri jaoks
        $users = User::orderBy('name')->get();

        return view('tasks.index', compact('tasks', 'users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::all();
        $companies = Company::all();
        $contacts = Contact::all();
        $deals = Deal::all();
        $users = User::all();
        
        return view('tasks.create', compact('customers', 'companies', 'contacts', 'deals', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:call,email,meeting,follow_up,development,bug_fix,content_creation,proposal_creation,testing,other',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:pending,in_progress,needs_testing,needs_clarification,completed,cancelled',
            'due_date' => 'nullable|date|after:now',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'deal_id' => 'nullable|exists:deals,id',
            'assignee_id' => 'nullable|exists:users,id',
            'price' => 'required|numeric|min:0'
        ]);

        $validated['user_id'] = Auth::id();

        Task::create($validated);

        return redirect()->route('tasks.index')
            ->with('success', 'Task created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        $task->load([
            'customer', 
            'company', 
            'contact', 
            'deal', 
            'timeEntries' => function($query) {
                $query->orderBy('start_time', 'desc');
            },
            'comments.user',
            'assignee',
            'user'
        ]);
        
        return view('tasks.show', compact('task'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task)
    {
        // Muuta saavad ainult looja ja vastutaja
        if ($task->user_id !== Auth::id() && $task->assignee_id !== Auth::id()) {
            abort(403, 'Sul pole õigust seda ülesannet muuta. Ainult ülesande looja ja vastutaja saavad seda teha.');
        }
        
        $customers = Customer::all();
        $companies = Company::all();
        $contacts = Contact::all();
        $deals = Deal::all();
        $users = User::all();
        
        return view('tasks.edit', compact('task', 'customers', 'companies', 'contacts', 'deals', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        // Muuta saavad ainult looja ja vastutaja
        if ($task->user_id !== Auth::id() && $task->assignee_id !== Auth::id()) {
            abort(403, 'Sul pole õigust seda ülesannet muuta. Ainult ülesande looja ja vastutaja saavad seda teha.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:call,email,meeting,follow_up,development,bug_fix,content_creation,proposal_creation,testing,other',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:pending,in_progress,needs_testing,needs_clarification,completed,cancelled',
            'due_date' => 'nullable|date|after:now',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'deal_id' => 'nullable|exists:deals,id',
            'assignee_id' => 'nullable|exists:users,id',
            'price' => 'required|numeric|min:0'
        ]);

        // Set completed_at if status is completed
        if ($validated['status'] === 'completed' && $task->status !== 'completed') {
            $validated['completed_at'] = now();
        } elseif ($validated['status'] !== 'completed') {
            $validated['completed_at'] = null;
        }

        $task->update($validated);

        return redirect()->route('tasks.index')
            ->with('success', 'Task updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        // Muuta saavad ainult looja ja vastutaja
        if ($task->user_id !== Auth::id() && $task->assignee_id !== Auth::id()) {
            abort(403, 'Sul pole õigust seda ülesannet muuta. Ainult ülesande looja ja vastutaja saavad seda teha.');
        }
        
        $task->delete();

        return redirect()->route('tasks.index')
            ->with('success', 'Task deleted successfully.');
    }
}
