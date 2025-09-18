<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContactController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $contacts = Contact::with(['customer', 'company', 'user'])
            ->where('user_id', Auth::id())
            ->paginate(15);

        return view('contacts.index', compact('contacts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $customers = Customer::where('user_id', Auth::id())->get();
        $companies = Company::where('user_id', Auth::id())->get();
        
        return view('contacts.create', compact('customers', 'companies'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:contacts,email',
            'phone' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'company_id' => 'nullable|exists:companies,id',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $validated['user_id'] = Auth::id();

        Contact::create($validated);

        return redirect()->route('contacts.index')
            ->with('success', 'Contact created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Contact $contact)
    {
        $this->authorize('view', $contact);
        
        $contact->load(['customer', 'company', 'deals', 'tasks']);
        
        return view('contacts.show', compact('contact'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Contact $contact)
    {
        $this->authorize('update', $contact);
        
        $customers = Customer::where('user_id', Auth::id())->get();
        $companies = Company::where('user_id', Auth::id())->get();
        
        return view('contacts.edit', compact('contact', 'customers', 'companies'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contact $contact)
    {
        $this->authorize('update', $contact);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:contacts,email,' . $contact->id,
            'phone' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'company_id' => 'nullable|exists:companies,id',
            'customer_id' => 'nullable|exists:customers,id',
        ]);

        $contact->update($validated);

        return redirect()->route('contacts.index')
            ->with('success', 'Contact updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contact $contact)
    {
        $this->authorize('delete', $contact);
        
        $contact->delete();

        return redirect()->route('contacts.index')
            ->with('success', 'Contact deleted successfully.');
    }
}
