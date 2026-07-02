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

        $query = Task::with(['customer', 'company', 'contact', 'deal', 'user', 'assignee'])
            ->withSum('timeEntries', 'duration')
            ->where('status', '!=', 'completed');

        // Favorite filter
        if ($request->has('favorite') && $request->favorite == 1) {
            $query->where('is_favorite', true);
        }

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

        $tasks = $query->orderBy('created_at', 'desc')->get();

        // Hangi kõik kasutajad filtri jaoks
        $users = User::orderBy('name')->get();

        return view('tasks.index', compact('tasks', 'users'));
    }

    /**
     * Display closed (completed) tasks.
     */
    public function closed(Request $request)
    {
        $query = Task::with(['customer', 'company', 'contact', 'deal', 'user', 'assignee'])
            ->withSum('timeEntries', 'duration')
            ->where('status', 'completed');

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

        $tasks = $query->orderBy('completed_at', 'desc')->get();

        // Hangi kõik kasutajad filtri jaoks
        $users = User::orderBy('name')->get();

        return view('tasks.closed', compact('tasks', 'users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::orderBy('first_name')->orderBy('last_name')->get();
        $companies = Company::orderBy('name')->get();
        $users     = User::orderBy('name')->get();

        // Defaults for the simplified create form: internal work by
        // default (Webfight self-customer + Webfight OÜ), assigned to
        // whoever is currently logged in.
        $defaultCustomer = Customer::where('first_name', 'Webfight')->first();
        $defaultCompany  = Company::where('name', 'Webfight OÜ')->first();

        return view('tasks.create', compact(
            'customers', 'companies', 'users',
            'defaultCustomer', 'defaultCompany',
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'                 => 'required|string|max:255',
            'description'           => 'nullable|string',
            'type'                  => 'nullable|in:call,email,meeting,follow_up,development,bug_fix,content_creation,proposal_creation,testing,other',
            'priority'              => 'nullable|in:low,medium,high,urgent',
            'status'                => 'nullable|in:pending,in_progress,needs_testing,needs_clarification,completed,cancelled',
            'due_date'              => 'nullable|date',
            'notes'                 => 'nullable|string',
            // Analytics fields are optional and only edited from the task
            // edit view — kept nullable so old workflows still work.
            'work_type'             => 'nullable|in:technical,design,copywriting,marketing,ecommerce,website,project,maintenance,other',
            'customer_id'           => 'nullable|exists:customers,id',
            'company_id'            => 'nullable|exists:companies,id',
            'contact_id'            => 'nullable|exists:contacts,id',
            'deal_id'               => 'nullable|exists:deals,id',
            'assignee_id'           => 'nullable|exists:users,id',
            'price'                 => 'nullable|numeric|min:0',
        ]);

        // Apply sensible defaults so the caller can send a minimal payload
        // from the simplified create form.
        $validated['type']        = $validated['type']     ?? 'other';
        $validated['priority']    = $validated['priority'] ?? 'low';
        $validated['status']      = $validated['status']   ?? 'pending';
        $validated['price']       = $validated['price']    ?? 0;
        $validated['assignee_id'] = $validated['assignee_id'] ?? Auth::id();
        $validated['user_id']     = Auth::id();

        Task::create($validated);

        return redirect()->route('tasks.index')
            ->with('success', 'Ülesanne loodud.');
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
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'type'        => 'nullable|in:call,email,meeting,follow_up,development,bug_fix,content_creation,proposal_creation,testing,other',
            'priority'    => 'nullable|in:low,medium,high,urgent',
            'status'      => 'nullable|in:pending,in_progress,needs_testing,needs_clarification,completed,cancelled',
            'due_date'    => 'nullable|date',
            'notes'       => 'nullable|string',
            'work_type'   => 'nullable|in:technical,design,copywriting,marketing,ecommerce,website,project,maintenance,other',
            'customer_id' => 'nullable|exists:customers,id',
            'company_id'  => 'nullable|exists:companies,id',
            'contact_id'  => 'nullable|exists:contacts,id',
            'deal_id'     => 'nullable|exists:deals,id',
            'assignee_id' => 'nullable|exists:users,id',
            'price'       => 'nullable|numeric|min:0',
        ]);

        // Apply the same defaults the store method uses so an edit that
        // omits an optional field doesn't wipe it back to null.
        $validated['type']     = $validated['type']     ?? $task->type     ?? 'other';
        $validated['priority'] = $validated['priority'] ?? $task->priority ?? 'low';
        $validated['status']   = $validated['status']   ?? $task->status   ?? 'pending';
        $validated['price']    = $validated['price']    ?? $task->price    ?? 0;

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

    /**
     * Toggle favorite status for a task.
     */
    public function toggleFavorite(Task $task)
    {
        $task->is_favorite = !$task->is_favorite;
        $task->save();

        return response()->json([
            'success' => true,
            'is_favorite' => $task->is_favorite
        ]);
    }
}
