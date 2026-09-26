{{-- Monthly fee of a „Püsiklient“ deal — shown only for that revenue model.
     Each month on the invoice day the CRM adds a task + Telegram with the RMP link. --}}
@php
    $d = $deal ?? null;
    $model = old('revenue_model', $d?->revenue_model);
@endphp
<div class="md:col-span-2 border border-indigo-200 bg-indigo-50 rounded-lg p-4 space-y-4"
     x-data="{ model: @js($model) }"
     x-init="document.getElementById('revenue_model')?.addEventListener('change', e => model = e.target.value)"
     x-show="model === 'retainer'" x-cloak>
    <div>
        <h3 class="text-sm font-semibold text-indigo-900">Kuutasu</h3>
        <p class="text-xs text-indigo-800 mt-0.5">Iga kuu arve päeval tuleb Telegrami meeldetuletus RMP lingiga ja tehingu juurde ülesanne „Kuuarve“. Arve teed RMP-s ise.</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <x-input-label for="retainer_amount" :value="__('Kuutasu (€, ilma KM-ta)')" />
            <x-text-input id="retainer_amount" name="retainer_amount" type="number" step="0.01" min="0" class="mt-1 block w-full"
                          :value="old('retainer_amount', $d?->retainer_amount)" />
            <x-input-error :messages="$errors->get('retainer_amount')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="retainer_note" :value="__('Arve rida')" />
            <x-text-input id="retainer_note" name="retainer_note" type="text" class="mt-1 block w-full" placeholder="nt SEO hooldus ja 4 blogiartiklit"
                          :value="old('retainer_note', $d?->retainer_note)" />
            <x-input-error :messages="$errors->get('retainer_note')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="retainer_start" :value="__('Esimene kuu')" />
            <x-text-input id="retainer_start" name="retainer_start" type="month" class="mt-1 block w-full"
                          :value="old('retainer_start', $d?->retainer_start?->format('Y-m'))" />
            <x-input-error :messages="$errors->get('retainer_start')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="retainer_months" :value="__('Kuude arv (tühi = kuni lõpetad)')" />
            <x-text-input id="retainer_months" name="retainer_months" type="number" min="1" max="120" class="mt-1 block w-full"
                          :value="old('retainer_months', $d?->retainer_months)" />
            <x-input-error :messages="$errors->get('retainer_months')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="retainer_day" :value="__('Arve kuupäev (iga kuu)')" />
            <x-text-input id="retainer_day" name="retainer_day" type="number" min="1" max="31" class="mt-1 block w-full"
                          :value="old('retainer_day', $d?->retainer_day ?? 1)" />
            <x-input-error :messages="$errors->get('retainer_day')" class="mt-2" />
        </div>
    </div>
</div>
