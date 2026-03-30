<?php

namespace App\Http\Controllers;

use App\Models\ClientAttribute;
use Illuminate\Http\Request;

class ClientAttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $attributes = ClientAttribute::orderBy('order')->get();
        return view('client-attributes.index', compact('attributes'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('client-attributes.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:client_attributes,name',
            'label' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        ClientAttribute::create($validated);

        return redirect()->route('client-attributes.index')
            ->with('success', 'Kliendi kategooria edukalt loodud.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ClientAttribute $clientAttribute)
    {
        return view('client-attributes.show', compact('clientAttribute'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ClientAttribute $clientAttribute)
    {
        return view('client-attributes.edit', compact('clientAttribute'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ClientAttribute $clientAttribute)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:client_attributes,name,' . $clientAttribute->id,
            'label' => 'required|string|max:255',
            'color' => 'required|string|max:7',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $clientAttribute->update($validated);

        return redirect()->route('client-attributes.index')
            ->with('success', 'Kliendi kategooria edukalt uuendatud.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ClientAttribute $clientAttribute)
    {
        $clientAttribute->delete();

        return redirect()->route('client-attributes.index')
            ->with('success', 'Kliendi kategooria edukalt kustutatud.');
    }
}
