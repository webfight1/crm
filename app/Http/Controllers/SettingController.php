<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function edit()
    {
        $settings = Setting::getSettings();
        return view('settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $settings = Setting::getSettings();

        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|max:50',
            'vat_number' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:50',
            'swift' => 'nullable|string|max:50',
            'quotation_terms' => 'nullable|string',
            'default_vat_rate' => 'required|numeric|min:0|max:100',
            'logo' => 'nullable|image|max:2048' // max 2MB
        ]);

        // Käsitle logo üleslaadimist
        if ($request->hasFile('logo')) {
            // Kustuta vana logo
            if ($settings->logo_path) {
                Storage::disk('public')->delete($settings->logo_path);
            }

            // Salvesta uus logo
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo_path'] = $path;
        }

        $settings->update($validated);

        return back()->with('success', __('Seaded on salvestatud!'));
    }
}
