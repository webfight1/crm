<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Customer;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class DealController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $deals = Deal::with(['customer', 'company', 'contact', 'user'])
            ->where('user_id', Auth::id())
            ->paginate(15);

        return view('deals.index', compact('deals'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::where('user_id', Auth::id())->get();
        $companies = Company::where('user_id', Auth::id())->get();
        $contacts = Contact::where('user_id', Auth::id())->get();
        
        return view('deals.create', compact('customers', 'companies', 'contacts'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'value' => 'required|numeric|min:0',
            'stage' => 'required|in:lead,qualified,proposal,negotiation,closed_won,closed_lost',
            'probability' => 'required|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date|after:today',
            'actual_close_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
        ]);

        $validated['user_id'] = Auth::id();

        Deal::create($validated);

        return redirect()->route('deals.index')
            ->with('success', 'Deal created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Deal $deal)
    {
        if ($deal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $deal->load(['customer', 'company', 'contact', 'tasks']);
        
        return view('deals.show', compact('deal'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Deal $deal)
    {
        if ($deal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $customers = Customer::where('user_id', Auth::id())->get();
        $companies = Company::where('user_id', Auth::id())->get();
        $contacts = Contact::where('user_id', Auth::id())->get();
        
        return view('deals.edit', compact('deal', 'customers', 'companies', 'contacts'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Deal $deal)
    {
        if ($deal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'value' => 'required|numeric|min:0',
            'stage' => 'required|in:lead,qualified,proposal,negotiation,closed_won,closed_lost',
            'probability' => 'required|integer|min:0|max:100',
            'expected_close_date' => 'nullable|date|after:today',
            'actual_close_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
            'company_id' => 'nullable|exists:companies,id',
            'contact_id' => 'nullable|exists:contacts,id',
        ]);

        $deal->update($validated);

        return redirect()->route('deals.index')
            ->with('success', 'Deal updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Deal $deal)
    {
        if ($deal->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $deal->delete();

        return redirect()->route('deals.index')
            ->with('success', 'Deal deleted successfully.');
    }
}
