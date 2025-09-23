<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ExternalCompany;
use App\Models\Customer;
use App\Models\Contact;
use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CompanyController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $companies = Company::with(['user'])
            ->where('user_id', Auth::id())
            ->paginate(15);

        return view('companies.index', compact('companies'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('companies.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'registrikood' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'employee_count' => 'nullable|integer|min:1',
            'annual_revenue' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,prospect',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();

        Company::create($validated);

        return redirect()->route('companies.index')
            ->with('success', 'Company created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Company $company)
    {
        if ($company->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $company->load(['customers', 'contacts', 'deals', 'tasks']);
        
        return view('companies.show', compact('company'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Company $company)
    {
        if ($company->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        return view('companies.edit', compact('company'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Company $company)
    {
        if ($company->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'registrikood' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'industry' => 'nullable|string|max:255',
            'employee_count' => 'nullable|integer|min:1',
            'annual_revenue' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive,prospect',
            'notes' => 'nullable|string',
        ]);

        $company->update($validated);

        return redirect()->route('companies.index')
            ->with('success', 'Company updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Company $company)
    {
        if ($company->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $company->delete();

        return redirect()->route('companies.index')
            ->with('success', 'Company deleted successfully.');
    }

    /**
     * Search companies from external database via AJAX
     */
    public function searchExternal(Request $request)
    {
        $query = $request->get('query');
        
        if (empty($query) || strlen($query) < 2) {
            return response()->json([]);
        }

        try {
            $companies = ExternalCompany::searchByName($query, 10);
            
            $results = $companies->map(function ($company) {
                // Saame täiendavad andmed seotud tabelitest
                $additionalData = $company->getAdditionalData();
                
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'registrikood' => $company->regcode,
                    'kmcode' => $company->kmcode,
                    'phone' => $additionalData['phones'][0] ?? '',
                    'email' => $additionalData['emails'][0] ?? '',
                    'website' => $additionalData['websites'][0] ?? '',
                    'additional_phones' => $additionalData['phones'],
                    'additional_emails' => $additionalData['emails'],
                    'additional_websites' => $additionalData['websites'],
                ];
            });

            return response()->json($results);
            
        } catch (\Exception $e) {
            \Log::error('External company search failed: ' . $e->getMessage());
            return response()->json(['error' => 'Otsing ebaõnnestus'], 500);
        }
    }
}
