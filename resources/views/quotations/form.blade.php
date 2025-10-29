@props(['quotation' => null, 'deal' => null])

<form method="POST" action="{{ $quotation ? route('quotations.update', $quotation) : route('quotations.store') }}" class="space-y-6">
    @csrf
    @if($quotation)
        @method('PUT')
    @endif

    @if($deal)
        <input type="hidden" name="deal_id" value="{{ $deal->id }}">
    @else
        <div>
            <x-input-label for="deal_id" :value="__('Deal')" />
            <select id="deal_id" name="deal_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <option value="">{{ __('Vali deal') }}</option>
                @foreach($deals as $deal)
                    <option value="{{ $deal->id }}" {{ (old('deal_id', $quotation?->deal_id) == $deal->id) ? 'selected' : '' }}>
                        {{ $deal->title }} @if($deal->customer) ({{ $deal->customer->full_name }}) @endif
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('deal_id')" class="mt-2" />
        </div>
    @endif

    <div>
        <x-input-label for="title" :value="__('Pealkiri')" />
        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $quotation?->title)" required />
        <x-input-error :messages="$errors->get('title')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="description" :value="__('Kirjeldus')" />
        <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $quotation?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-2" />
    </div>

    <!-- Pakkumise read -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">{{ __('Pakkumise read') }}</h3>
            <button type="button" onclick="addItem()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                {{ __('Lisa rida') }}
            </button>
        </div>
        
        <div id="items-container" class="space-y-4">
            @if($quotation && $quotation->items->count() > 0)
                @foreach($quotation->items as $item)
                    <div class="item-row grid grid-cols-12 gap-4 items-start">
                        <div class="col-span-5">
                            <x-input-label :value="__('Kirjeldus')" />
                            <textarea name="items[{{ $loop->index }}][description]" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>{{ $item->description }}</textarea>
                        </div>
                        <div class="col-span-2">
                            <x-input-label :value="__('Kogus')" />
                            <x-text-input type="number" name="items[{{ $loop->index }}][quantity]" class="mt-1 block w-full" value="{{ $item->quantity }}" step="0.01" required />
                        </div>
                        <div class="col-span-2">
                            <x-input-label :value="__('Ühik')" />
                            <x-text-input type="text" name="items[{{ $loop->index }}][unit]" class="mt-1 block w-full" value="{{ $item->unit }}" required />
                        </div>
                        <div class="col-span-2">
                            <x-input-label :value="__('Ühiku hind')" />
                            <x-text-input type="number" name="items[{{ $loop->index }}][unit_price]" class="mt-1 block w-full" value="{{ $item->unit_price }}" step="0.01" required />
                        </div>
                        <div class="col-span-1 pt-7">
                            <button type="button" onclick="this.closest('.item-row').remove()" class="text-red-600 hover:text-red-900">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <x-input-label for="vat_rate" :value="__('Käibemaksu määr (%)')" />
            <x-text-input id="vat_rate" name="vat_rate" type="number" class="mt-1 block w-full" :value="old('vat_rate', $quotation?->vat_rate ?? $settings->default_vat_rate)" step="0.01" required />
            <x-input-error :messages="$errors->get('vat_rate')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="valid_until" :value="__('Kehtiv kuni')" />
            <x-text-input id="valid_until" name="valid_until" type="date" class="mt-1 block w-full" :value="old('valid_until', $quotation?->valid_until?->format('Y-m-d'))" />
            <x-input-error :messages="$errors->get('valid_until')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="terms" :value="__('Maksetingimused')" />
            <textarea id="terms" name="terms" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('terms', $quotation?->terms ?? $settings->quotation_terms) }}</textarea>
            <x-input-error :messages="$errors->get('terms')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="notes" :value="__('Märkused')" />
            <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes', $quotation?->notes) }}</textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
        </div>
    </div>

    <div class="flex justify-end space-x-4">
        <a href="{{ url()->previous() }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
            {{ __('Tühista') }}
        </a>
        <x-primary-button>
            {{ $quotation ? __('Salvesta muudatused') : __('Loo pakkumine') }}
        </x-primary-button>
    </div>
</form>

@push('scripts')
<script>
    function addItem() {
        const container = document.getElementById('items-container');
        const index = container.children.length;
        
        const template = `
            <div class="item-row grid grid-cols-12 gap-4 items-start">
                <div class="col-span-5">
                    <label class="block font-medium text-sm text-gray-700">{{ __('Kirjeldus') }}</label>
                    <textarea name="items[${index}][description]" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required></textarea>
                </div>
                <div class="col-span-2">
                    <label class="block font-medium text-sm text-gray-700">{{ __('Kogus') }}</label>
                    <input type="number" name="items[${index}][quantity]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value="1" step="0.01" required />
                </div>
                <div class="col-span-2">
                    <label class="block font-medium text-sm text-gray-700">{{ __('Ühik') }}</label>
                    <input type="text" name="items[${index}][unit]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value="tk" required />
                </div>
                <div class="col-span-2">
                    <label class="block font-medium text-sm text-gray-700">{{ __('Ühiku hind') }}</label>
                    <input type="number" name="items[${index}][unit_price]" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value="0" step="0.01" required />
                </div>
                <div class="col-span-1 pt-7">
                    <button type="button" onclick="this.closest('.item-row').remove()" class="text-red-600 hover:text-red-900">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', template);
    }

    // Lisa esimene rida, kui pole ühtegi rida
    if (document.querySelectorAll('.item-row').length === 0) {
        addItem();
    }
</script>
@endpush
