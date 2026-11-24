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
    @php
        $formItems = old('items', $quotation?->items->map(fn ($i) => [
            'type' => $i->type ?? 'item',
            'description' => $i->description,
            'quantity' => $i->quantity,
            'unit' => $i->unit,
            'unit_price' => $i->unit_price,
        ])->values()->all() ?? []);
    @endphp
    <div>
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">{{ __('Pakkumise read') }}</h3>
            <div class="flex gap-2">
                <button type="button" onclick="addItem()" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    {{ __('Lisa rida') }}
                </button>
                <button type="button" onclick="addItem({type: 'text'})" class="bg-white border border-blue-500 text-blue-600 hover:bg-blue-50 font-bold py-2 px-4 rounded" title="{{ __('Pealkiri või selgitus ilma hinnata') }}">
                    {{ __('Lisa tekstirida') }}
                </button>
            </div>
        </div>
        <x-input-error :messages="$errors->get('items')" class="mb-2" />
        @foreach($errors->getMessages() as $key => $msgs)
            @if(str_starts_with($key, 'items.'))
                <x-input-error :messages="$msgs" class="mb-2" />
            @endif
        @endforeach

        <div id="items-container" class="space-y-4"></div>
    </div>

    {{-- Live totals — recomputed in JS on every input change. Server is the
         source of truth on save; this panel exists only to give the operator
         immediate visual feedback while editing rows. --}}
    <div id="totals-panel" class="bg-gray-50 border border-gray-200 rounded-lg p-4">
        <div class="grid grid-cols-3 gap-4 text-sm">
            <div>
                <div class="text-gray-500">{{ __('Vahesumma') }}</div>
                <div class="text-lg font-semibold text-gray-900"><span id="totals-subtotal">0.00</span> €</div>
            </div>
            <div>
                <div class="text-gray-500">{{ __('Käibemaks') }} (<span id="totals-vat-rate">0</span>%)</div>
                <div class="text-lg font-semibold text-gray-900"><span id="totals-vat">0.00</span> €</div>
            </div>
            <div>
                <div class="text-gray-500">{{ __('Kokku') }}</div>
                <div class="text-xl font-bold text-indigo-700"><span id="totals-grand">0.00</span> €</div>
            </div>
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
    const itemInput = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';
    const trashIcon = `<svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>`;
    // Never reuse an index: after a removal, children.length would collide
    // with a surviving row and overwrite it on submit.
    let itemIndex = 0;

    function esc(v) {
        const d = document.createElement('div');
        d.textContent = v ?? '';
        return d.innerHTML.replace(/"/g, '&quot;');
    }

    // Rows are saved in on-screen order (POST keeps DOM order), so moving the
    // element is enough; index names stay unique.
    function moveItem(btn, dir) {
        const row = btn.closest('.item-row');
        const sibling = dir < 0 ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling) return;
        dir < 0 ? sibling.before(row) : sibling.after(row);
    }

    function addItem(item = {}) {
        const container = document.getElementById('items-container');
        const i = itemIndex++;
        const isText = item.type === 'text';
        const removeBtn = `<div class="col-span-1 pt-7 flex items-center gap-2">
                <div class="flex flex-col leading-none">
                    <button type="button" onclick="moveItem(this, -1)" class="text-gray-500 hover:text-gray-900 px-1" title="{{ __('Liiguta üles') }}">&#9650;</button>
                    <button type="button" onclick="moveItem(this, 1)" class="text-gray-500 hover:text-gray-900 px-1" title="{{ __('Liiguta alla') }}">&#9660;</button>
                </div>
                <button type="button" onclick="this.closest('.item-row').remove()" class="text-red-600 hover:text-red-900" title="{{ __('Eemalda') }}">${trashIcon}</button>
            </div>`;

        const template = isText ? `
            <div class="item-row grid grid-cols-12 gap-4 items-start">
                <input type="hidden" name="items[${i}][type]" value="text">
                <div style="grid-column: span 11 / span 11;">
                    <label class="block font-medium text-sm text-gray-700">{{ __('Tekst') }} <span class="text-gray-400 font-normal">({{ __('ilma hinnata') }})</span></label>
                    <textarea name="items[${i}][description]" rows="6" class="${itemInput} bg-gray-50" required>${esc(item.description)}</textarea>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Vormindus') }}: <code>## Pealkiri</code> · <code>**paks tekst**</code> · <code>- loetelu punkt</code></p>
                </div>
                ${removeBtn}
            </div>` : `
            <div class="item-row grid grid-cols-12 gap-4 items-start">
                <input type="hidden" name="items[${i}][type]" value="item">
                <div class="col-span-5">
                    <label class="block font-medium text-sm text-gray-700">{{ __('Kirjeldus') }}</label>
                    <textarea name="items[${i}][description]" rows="2" class="${itemInput}" required>${esc(item.description)}</textarea>
                </div>
                <div class="col-span-2">
                    <label class="block font-medium text-sm text-gray-700">{{ __('Kogus') }}</label>
                    <input type="number" name="items[${i}][quantity]" class="${itemInput}" value="${esc(item.quantity ?? 1)}" step="0.01" required />
                </div>
                <div class="col-span-2">
                    <label class="block font-medium text-sm text-gray-700">{{ __('Ühik') }}</label>
                    <input type="text" name="items[${i}][unit]" class="${itemInput}" value="${esc(item.unit ?? 'tk')}" required />
                </div>
                <div class="col-span-2">
                    <label class="block font-medium text-sm text-gray-700">{{ __('Ühiku hind') }}</label>
                    <input type="number" name="items[${i}][unit_price]" class="${itemInput}" value="${esc(item.unit_price ?? 0)}" step="0.01" required />
                </div>
                ${removeBtn}
            </div>`;

        container.insertAdjacentHTML('beforeend', template);
        recomputeTotals();
    }

    const initialItems = @json(array_values($formItems));
    initialItems.forEach(it => addItem(it));
    // Lisa esimene rida, kui pole ühtegi rida
    if (initialItems.length === 0) {
        addItem();
    }

    // ─── Live totals ────────────────────────────────────────────────────────
    // Listens at the form level so dynamically-added rows are covered without
    // re-binding. Recomputes on every input change (quantity, unit_price,
    // vat_rate). Server still recalculates on save — this is preview only.
    function fmt(n) {
        return (Math.round(n * 100) / 100).toFixed(2);
    }
    function recomputeTotals() {
        let subtotal = 0;
        document.querySelectorAll('.item-row').forEach(row => {
            const qty   = parseFloat(row.querySelector('[name*="[quantity]"]')?.value)   || 0;
            const price = parseFloat(row.querySelector('[name*="[unit_price]"]')?.value) || 0;
            subtotal += qty * price;
        });
        const vatRate = parseFloat(document.getElementById('vat_rate')?.value) || 0;
        const vat     = subtotal * vatRate / 100;
        const grand   = subtotal + vat;
        document.getElementById('totals-subtotal').textContent = fmt(subtotal);
        document.getElementById('totals-vat-rate').textContent  = vatRate;
        document.getElementById('totals-vat').textContent       = fmt(vat);
        document.getElementById('totals-grand').textContent     = fmt(grand);
    }
    function bindTotals() {
        // Bind on document, not on the form: layout includes the navigation
        // logout form earlier in the DOM so querySelector('form') would
        // return that instead of the quotation form. Document-level capture
        // is robust and trivially cheap.
        document.addEventListener('input',  recomputeTotals);
        document.addEventListener('change', recomputeTotals);
        // Row removal goes through onclick handlers that don't dispatch
        // 'input'; use a click delegate plus a microtask to recompute AFTER
        // the .item-row has been detached.
        document.addEventListener('click', (e) => {
            if (e.target.closest('.item-row button[type="button"]')) {
                queueMicrotask(recomputeTotals);
            }
        });
        recomputeTotals();
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindTotals);
    } else {
        bindTotals();
    }
</script>
@endpush
