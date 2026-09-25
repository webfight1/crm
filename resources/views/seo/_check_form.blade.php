@php $builtin = $check?->isBuiltin() ?? false; @endphp
<form method="POST" action="{{ $action }}" class="grid md:grid-cols-6 gap-3 text-sm">
    @csrf
    @if($method !== 'POST') @method($method) @endif

    <div class="md:col-span-4">
        <x-input-label value="Nimetus" />
        <x-text-input name="label" :value="$check?->label" required class="mt-1 block w-full" placeholder="nt: Teenuste lehtedel on hinnainfo" />
    </div>
    <div>
        <x-input-label value="Kaal (1–5)" />
        <x-text-input name="weight" type="number" min="1" max="5" :value="$check?->weight ?? 2" class="mt-1 block w-full" />
    </div>
    <div class="flex items-end pb-2">
        <label class="inline-flex items-center gap-2">
            <input type="checkbox" name="enabled" value="1" @checked($check?->enabled ?? true) class="rounded border-gray-300 text-indigo-600">
            <span>Sees</span>
        </label>
    </div>

    @unless($builtin)
        <div class="md:col-span-6">
            <x-input-label value="Küsimus AI-le (vastatakse lehe sisu põhjal)" />
            <textarea name="question" rows="2" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      placeholder="nt: Kas avalehel on kirjas, millises piirkonnas ettevõte teenust pakub?">{{ $check?->question }}</textarea>
        </div>
    @endunless

    <div class="md:col-span-6">
        <x-input-label value="Miks see kliendile oluline on (läheb auditi kokkuvõttesse)" />
        <x-text-input name="client_explanation" :value="$check?->client_explanation" class="mt-1 block w-full" />
    </div>

    <div class="md:col-span-3">
        <x-input-label value="Parandus pakkumises (rea tekst)" />
        <x-text-input name="fix_title" :value="$check?->fix_title" class="mt-1 block w-full" placeholder="tühi = ei lähe pakkumisse" />
    </div>
    <div>
        <x-input-label value="Hind €" />
        <x-text-input name="fix_price" type="number" step="0.01" min="0" :value="$check?->fix_price" class="mt-1 block w-full" />
    </div>
    <div>
        <x-input-label value="Kogus" />
        <x-text-input name="fix_quantity" type="number" step="0.01" min="0" :value="$check?->fix_quantity ?? 1" class="mt-1 block w-full" />
    </div>
    <div>
        <x-input-label value="Ühik" />
        <x-text-input name="fix_unit" :value="$check?->fix_unit ?? 'tk'" class="mt-1 block w-full" />
    </div>

    <div class="md:col-span-6 flex justify-end">
        <x-primary-button>{{ $check ? 'Salvesta' : 'Lisa kontroll' }}</x-primary-button>
    </div>
</form>
