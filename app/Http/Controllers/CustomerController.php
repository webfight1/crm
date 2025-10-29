<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CustomerController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $customers = Customer::with(['company'])
            ->paginate(15);

        return view('customers.index', compact('customers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $companies = Company::all();
        return view('customers.create', compact('companies'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'status' => 'required|in:active,inactive,prospect',
            'notes' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        Customer::create($validated);

        return redirect()->route('customers.index')
            ->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        $customer->load(['company', 'contacts', 'deals', 'tasks']);
        
        return view('customers.show', compact('customer'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        $companies = Company::all();
        
        return view('customers.edit', compact('customer', 'companies'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email,' . $customer->id,
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'status' => 'required|in:active,inactive,prospect',
            'notes' => 'nullable|string',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return redirect()->route('customers.index')
            ->with('success', 'Customer deleted successfully.');
    }

    /**
     * Get customer and company details by customer ID
     */
    public function getDetails(Customer $customer)
    {
        $data = [
            'customer' => [
                'email' => $customer->email ?? '',
                'phone' => $customer->phone ?? '',
                'address' => $customer->address ?? '',
                'city' => $customer->city ?? '',
                'state' => $customer->state ?? '',
                'postal_code' => $customer->postal_code ?? '',
                'country' => $customer->country ?? ''
            ],
            'company' => null,
            'contacts' => []
        ];

        // Get company details if customer is associated with a company
        if ($customer->company) {
            $data['company'] = [
                'id' => $customer->company->id,
                'name' => $customer->company->name,
                'email' => $customer->company->email ?? '',
                'phone' => $customer->company->phone ?? '',
                'address' => $customer->company->address ?? '',
                'city' => $customer->company->city ?? '',
                'state' => $customer->company->state ?? '',
                'postal_code' => $customer->company->postal_code ?? '',
                'country' => $customer->company->country ?? ''
            ];

            // Get contacts associated with the company
            $data['contacts'] = $customer->company->contacts->map(function($contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->full_name,
                    'email' => $contact->email ?? '',
                    'phone' => $contact->phone ?? '',
                    'position' => $contact->position ?? ''
                ];
            });
        }

        return response()->json($data);
    }
}
